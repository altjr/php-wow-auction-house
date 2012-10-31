<?php


	define('WOW_REALMSLUG','shadow-council');	// The name of your realm, URL friendly (i.e the slug for C'Thun is cthun).

	define('DB_HOST','localhost');			// Your database host.
	define('DB_USER','root');			// Your database username.
	define('DB_PASS','');				// Your database password, for above username.
	define('DB_DBNAME','wow');			// And your target database, the name of the database itself.

	define('MODE_SNAPSHOT',true);			// If true, it keeps only a "snapshot" of the AH as it is.
							// Otherwise, it keeps the historical entries intact.

	// To reduce magic numbers, I use these constants for faction.
	define('FACTION_ALLIANCE',1);	
	define('FACTION_HORDE',2);	
	define('FACTION_NEUTRAL',3);

	define('FACTION_DEFAULT',FACTION_ALLIANCE); // set your default faction here.

	define('HTTP_MAX_TRIES',3);	// How many times will we retry on an HTTP error? (Sometimes they fail...)
	define('HTTP_POLITE_SLEEP',2);	// Sometimes http requests fail and I fear it's cause it happens too fast, so, I sleep a little.

	// Should you need more memory for this script, uncomment the following line.
	// ini_set('memory_limit','512M');

	$ah = new auctionHouse($argv);

	class auctionHouse {

		var $dbh = false;	// The database handle.
		var $live = true;	// Are we live? If not, we're in a test mode.
		var $url_modified = "http://us.battle.net/api/wow/auction/data/";
		var $faction = 0;
		var $wowstamp;		// The time stamp from the wow api
		
		function auctionHouse($argv) {

			// Ok, first thing's first, let's parse the command line parameters.
			// Right now, it's just faction.
			$this->setArguments($argv);

			// Not far behind, now set the URL to check if it's been modified based on the realm.
			$this->url_modified .= WOW_REALMSLUG;

			// We drop into a method to check for the update.
			$url = $this->checkForUpdate();

			if (!empty($url) || !$this->live) {

				if ($this->live) {

				
					echo "Beginning auction download...\n";

					$file = false; 	// At first we assume the file get is bad, in order to go into a loop processing it (if it is)
					$tries = 0;	// A count of the number of tries to download it.

					// Sleep for just a moment.
					sleep(HTTP_POLITE_SLEEP);

					while ($file === false && $tries < HTTP_MAX_TRIES) {
						$tries++;

						$file = file_get_contents($url);
						// file_put_contents("/home/doug/wow.txt",$file);

						if (!$file) {
							echo "HTTP get failed, try #".$tries."\n";
						} else {
							echo "Remote has new data. Setting update token info.\n";
							$this->goSQL("UPDATE token SET wowstamp='".$this->wowstamp."',indate=NOW() WHERE id=1");
						}

					}

					if (!$file) {
						echo "Failed to download, try it again.\n";
						die();
					}
				

				} else {

					echo "Forced test with flat file...\n";
					// We're not live. Soooo...
					// We're reading a test file.
					$file = file_get_contents("/home/doug/wow.txt");

				}

				$auctions = json_decode($file);

				$this->insertAuctions($auctions);

				// print_r($auctions);

			}


		}

		function setArguments($argv) {

			if (isset($argv[1])) {
				
				switch ($argv[1]) {
					case "horde": 
						$this->faction = FACTION_HORDE;
						break;
					case "alliance": 
						$this->faction = FACTION_ALLIANCE;
						break;
					case "neutral": 
						$this->faction = FACTION_NEUTRAL;
						break;
					default: 
						die("Unknown faction\n");
				}
			} else {
				$this->faction = FACTION_DEFAULT;
			}

		}

		function insertAuctions($auctions) {

			// Let's get out time lengths from the db.
			// We use these to key on a tiny int, instead of say...
			// Using a buncha chars.
			$rawlen = $this->goSQL_assoc($this->goSQL("SELECT * FROM time_length"));
			$lengths = array();
			foreach ($rawlen as $r) {
				$lengths[$r['value']] = $r['id'];
			}

			// Ok, if it's snapshot mode, we can whipe this table.
			// So let's do that if need be.
			if (MODE_SNAPSHOT) {
				echo "Clearing table contents under snapshot mode.\n";
				$this->goSQL("TRUNCATE TABLE auctions");
			}

			$this->goSQL("BEGIN WORK");

			// We pick which part of the file we're going to read based on your selected faction.
			switch ($this->faction) {

				case FACTION_ALLIANCE:
					$faction_auction = $auctions->alliance;
					break;
				case FACTION_HORDE:
					$faction_auction = $auctions->horde;
					break;
				case FACTION_NEUTRAL:
					$faction_auction = $auctions->neutral;
					break;

			}

			$total = count($faction_auction->auctions);
			echo "Beginning item insertion... (".$total." items)\n";

			$count = 0;
			foreach ($faction_auction->auctions as $item) {
				$count++;
				if (($count % 5000) == 0) {
					echo "Processed ".$count." / ".$total."\n";
				}
				// print_r($item);

				// Ok now, we're down to the actual item.
				// So let's construct a query for it.
				$q = "REPLACE INTO auctions (id,item,owner,bid,buyout,quantity,timeLeft,lastupdate) VALUES (
				'".$item->auc."',
				'".$item->item."',
				'".$item->owner."',
				'".$item->bid."',
				'".$item->buyout."',
				'".$item->quantity."',
				'".$item->timeLeft."',
				NOW()
				)";

				$this->goSQL($q);

			}

			$this->goSQL("COMMIT");

			echo "Finished item insert!\n";

			

			/*

			stdClass Object
			(
			    [realm] => stdClass Object
				(
				    [name] => Shadow Council
				    [slug] => shadow-council
				)

			    [alliance] => stdClass Object
				(
				    [auctions] => Array
					(
					    [0] => stdClass Object
						(
						    [auc] => 1613445537
						    [item] => 52979
						    [owner] => Aalìyah
						    [bid] => 92960
						    [buyout] => 104665
						    [quantity] => 5
						    [timeLeft] => VERY_LONG
						)



			*/

		}
		

		function checkForUpdate() {

			echo "Checking for update...\n";

			$token = $this->goSQL_row($this->goSQL("SELECT * FROM token WHERE id=1"));

			echo "Last updated @ ".$token['indate']."\n";

			$json = file_get_contents($this->url_modified);
			$update = json_decode($json);
			$this->wowstamp = $update->files[0]->lastModified;

			// print_r($update);
			// print_r($update->files[0]->url);

			if ($this->wowstamp != $token['wowstamp']) {
				// Go ahead and update the token table.
				return $update->files[0]->url;
			} else {
				echo "No update to be made, currently up to date.\n";
				return "";
			}
			
		}

		// Global functions, e.g. for the database.
		function goSQL ($statement) { // database wrapper

			if (!$this->dbh) {
				try {
					$this->dbh = new PDO("mysql:host=".DB_HOST.";dbname=".DB_DBNAME, DB_USER, DB_PASS); 
				} catch(PDOException $e) {
					print ("Could not connect to server.\n");
					print ("getMessage(): " . $e->getMessage () . "\n");
					die("Database error.\n");
				}

			}
	
			$result = $this->dbh->query($statement);
			return $result;

		}

		function goSQL_row($result) {
			$returner = array();
			$row = $this->goSQL_assoc($result);
			foreach ($row as $r) {
				foreach ($r as $key => $val) {
					$returner[$key] = $val;
				}
				break;
			}
			return $returner;
		}


		function goSQL_assoc($result) {
			$returner = array();
			while ($row = $result->fetch (PDO::FETCH_ASSOC)) {
				array_push($returner,$row);
			}
			return $returner;
		}

	}

	

?>

== PHP-MySQL WoW (World of Warcraft) Auction House "Snapshot" ==

This app takes a snapshot of the Auction House (AH) on WoW, by your realm and stores it in MySQL it does so by using MySQL.

== Requirements. ==

PHP (>= 5.3.0)
MySQL (with InnoDB)

Setting up these components is outside the scope of this project.

== Notes ==

The .sql file includes an "item" table, so you can relate the item ID's back to the item table.

This is just a starting point for your reference. You may wish to flesh out your implementation by updating the items table.

== Install! ==

It should be relatively straight forward.

1. Check out the code.

2. Setup your MySQL database.

  Create a new database in your mysql client. 

	mysql> CREATE DATABASE wow;

  Now process the wow.sql file that comes with this project against that database, a la:

	[user@host]$ mysql -u username_here -p wow < wow.sql

  That will setup the data structures you need, and give you an initial list of items, as well.

3. Configure the basic settings at the top of the auctionHouse.php file

  You'll see a series of parameters at the top of the file, which are the mysql database connection
  properties. You'll want to fill those out, and read the few other options to.

  Such as, you can set your default faction.


4. Run the script!

  A basic run will look something like:

	[user@host]$ php -q auctionHouse.php alliance

  The first argument is the name of the faction you wish to take a snapshot of. 

  The valid values for it are:
    alliance
    horde
    neutral

  You can also set a default in the defines at the top of the file, and, then not use an argument, if you wish.
  
  The "default default" is Alliance, sorry Horde guys :)

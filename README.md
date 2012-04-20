PHP MySQL DUMP
=============

Functions for dump the entire MySQL database into a .sql file ready to be imported or import a .sql file.

How to use
----------

### Dumping

Use this to get an export of the MySQL database - NOTE: doesn't support FULLTEXT KEYS at present

    $mysql_error = '';
    if(!$dump = pmd_mysql_dump($db_host, $db_username, $db_password, $db_name, &$mysql_error))
       echo $mysql_error;
    else
       echo $dump;  // write to file or whatever you want

### Importing

    if(!pmd_mysql_dump_import($db_host, $db_username, $db_password, $db_name, $dump, &$mysql_error))
        echo $mysql_error;

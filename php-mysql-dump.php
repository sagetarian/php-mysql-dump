<?php

    //$mysql_error = '';
    //if(!$dump = pmd_mysql_dump('localhost', 'root', '', 'test',&$mysql_error))
    //   echo $mysql_error;
    //else
    //   echo $dump;
    
    //if(!pmd_mysql_dump_import('localhost', 'root', '', 'test',$dump,&$mysql_error))
    //   echo $mysql_error;
    
    function pmd_mysql_dump($db_host, $db_username, $db_password, $db_name, $last_result = NULL) {
        if(!$db_connection) :
            $db_connection = new mysqli($db_host, $db_username, $db_password);
            if(!$db_connection || !$db_connection->select_db($db_name)) {
               if($db_connection)
                    $last_result = mysqli_connect_error();
               else 
                    $last_result = "Unable to connect to mysql database";
                return NULL;
            }
        endif;
        
        // list tables
        $sql = 'SHOW TABLES FROM '.$db_name;
        $result = $db_connection->query($sql);
        if (!$result) {
            $last_result = $db_connection->error;
            return NULL;
        }
        
        $dump = "-- WP Live Server Deploy MySQL Dump 0.2\n"
        ."--\n"
        ."-- Host: ".$db_host."    Database: ".$db_name."\n"
        ."-- ------------------------------------------------------\n"
        ."-- Server version	".mysql_get_server_info()."\n";
        
        $column_Set = false;
        // go through each table
        while ($row = mysqli_fetch_row($result)) :
            $table = $row[0];
            
            $dump .= "-- \n-- Table structure for table `".$table."`\n--\n\n";
            
            $dump .= "DROP TABLE IF EXISTS `".$table."`;\n";
            $dump .= "CREATE TABLE `".$table."` (\n";
            // list columns
            $sql = 'SHOW COLUMNS FROM `'.$table.'`';
            $cresult = $db_connection->query($sql);
            if (!$cresult) {
                $last_result = $db_connection->error;
                return NULL;
            }
            $primary_key = "";
            $keys = "";
            
            while ($column = mysqli_fetch_assoc($cresult)) :
                $scolumn = "`".$column['Field']."` ";
                $scolumn .= ($column['Type'])." ";
                if($column['Null'] == "NO") $scolumn .= "NOT NULL ";
                if(isset($column['Default']) || !($column['Null'] == "NO" && $column['Default'] == NULL)) {
                    if(!isset($column['Default']) && !is_string($column['Default'])) $scolumn .= "NULL ";
                    else $scolumn .= "DEFAULT '{$column['Default']}' ";
                }
                $scolumn .= strtoupper($column['Extra'])." ";
                $dump .= "  ".trim($scolumn).",\n";
            endwhile;

            $sql = 'SHOW INDEXES FROM `'.$table.'`';
            $cresult = $db_connection->query($sql);
            if (!$cresult) {
                $last_result = $db_connection->error;
                return NULL;
            }
            $primary_key = "";
            $keys = "";
            
            $indexes = array();
            
            while ($column = mysqli_fetch_assoc($cresult)) :
                if(!@$indexes[$column['Key_name']]) :
                    $indexes[$column['Key_name']] = $column;
                else :
                    $indexes[$column['Key_name']]['Column_name'] = $indexes[$column['Key_name']]['Column_name'] .= '`, `'.$column['Column_name'];
                endif;
            endwhile;
            
            foreach ($indexes as $column) :
                $type = null;
                if(!$column['Non_unique']) :
                    if($column['Key_name'] == "PRIMARY")
                        $type = "PRIMARY";
                    else
                        $type = "UNIQUE";
                else :
                    $type = "KEY";
                endif;
                switch($type) :
                    case 'PRIMARY':
                        $dump .= "  PRIMARY KEY (`".$column['Column_name']."`),\n";
                        break;
                    case 'UNIQUE':
                        $dump .= "  UNIQUE KEY `".$column['Key_name']."` (`".$column['Column_name']."`),\n";
                        break;
                    case 'KEY':
                        $dump .= "  KEY `".$column['Key_name']."` (`".$column['Column_name']."`),\n";
                        break;
                endswitch;
            endforeach;
            
            $sql = 'SHOW TABLE STATUS LIKE "'.$table.'"';
            $cresult = $db_connection->query($sql);
            if (!$cresult) {
                $last_result = $db_connection->error;
                return NULL;
            }
            $attributes = "";
            
            while ($column = mysqli_fetch_assoc($cresult)) :
                $attributes .= " ENGINE=".$column['Engine'];
                if($column['Auto_increment']) $attributes .= " AUTO_INCREMENT=".$column['Auto_increment'];
                if($column['Collation']) $attributes .= " DEFAULT CHARSET=".current(explode('_',$column['Collation']));
            endwhile;

            // cut of the last comma
            $dump = trim($dump);
            if($dump[strlen($dump)-1] == ',') $dump[strlen($dump)-1] = "\n";

            $dump .= ") $attributes;\n";
            
            $dump .= "-- \n-- Dumping data for table `".$table."`\n--\n\n";
            $dump .= "LOCK TABLES `".$table."` WRITE;\n";
            //$dump .= "/*!40000 ALTER TABLE `".$table."` DISABLE KEYS */;\n";
            
            $sql = 'SELECT * FROM `'.$table.'`';
            $cresult = $db_connection->query($sql);
            if (!$cresult) {
                $last_result = $db_connection->error;
                return NULL;
            }
            
            while ($column = mysqli_fetch_assoc($cresult)) :
                $dump .= "INSERT INTO `$table` VALUES (";
                $first = true;
                foreach($column as $v) {
                    if(!$first) $dump .= ", ";
                    $dump .= "'".mysql_real_escape_string($v)."'";
                    $first = false;
                }
                $dump .= ");\n";
                $column;
            endwhile;
            
            //$dump .= "/*!40000 ALTER TABLE `".$table."` ENABLE KEYS */;\n";
            $dump .= "UNLOCK TABLES;\n";
            
            
        endwhile;
        mysqli_free_result($result);
        $db_connection->close();
        return $dump;
    }
    
    function pmd_mysql_dump_import($db_host, $db_username, $db_password, $db_name, $sql_dump, $last_result = NULL) {
        $db_connection = @new mysqli($db_host, $db_username, $db_password);
        if(mysqli_connect_error()) {
            $last_result = mysqli_connect_error();
            return NULL;
        }
        
        if(!$db_connection->select_db($db_name)) {
           if($db_connection)
                $last_result = $db_connection->error;
           else 
                $last_result = "Unable to connect to mysql database";
           $db_connection->close();
           return NULL;
        }
        
        $contents = explode("\n",$sql_dump);
        $templine = '';
        foreach($contents as $line) {
            if (substr($line, 0, 2) == '/*' || substr($line, 0, 2) == '--' || $line == '')
                continue;
            
            $templine .= $line;
            if (substr(trim($line), -1, 1) == ';') {
                if(!$db_connection->query($templine)) {
                    $last_result .= $db_connection->error."\n";
                }
                $templine = '';
            }
        }
        $db_connection->close();
        return true;
    }

?>

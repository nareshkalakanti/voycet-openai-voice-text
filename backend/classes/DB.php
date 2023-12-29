<?php
    class DB{
        function connect(){
            try{
                $db = new PDO('mysql:host=localhost; dbname=MyWhisper', 'root', '');
                 return $db;
            }catch(PDOException $error){
                echo 'Connection Failed: ' . $error;
            }
        }
    }

    
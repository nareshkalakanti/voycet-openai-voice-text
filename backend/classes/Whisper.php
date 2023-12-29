<?php
    class Whisper{
        public $error;
        public $dataType;
        public $file;
        public $lang;
        public $content;
        private $DB;

        public function __construct(){
            $db = new DB;
            $this->DB = $db->connect();
        }

        public function errors(){
            return $this->error;
        }

        public function getApiUrl(){
            if($this->dataType === "ASR"){
                return "https://api.openai.com/v1/audio/transcriptions";
            }else{
                return "https://api.openai.com/v1/chat/completions";
            }
        }

        public function getHeader(){
            if($this->dataType === "ASR"){
                return [
                    'Authorization: Bearer ' . API_TOKEN,
                    'Content-Type: multipart/form-data'
                ];
            }else{
                return [
                    'Authorization: Bearer ' . API_TOKEN,
                    'Content-Type: application/json'
                ];
            }
        }

        public function getData(){
            if($this->dataType === "ASR"){
                return [
                    'file' => $this->file,
                    'model' => 'whisper-1'
                ];
            }else{
                return json_encode([
                    'model' => 'gpt-3.5-turbo',
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'You will be provided with a text, and your task is to translate it into ' . $this->lang
                        ],
                        [
                            'role' => 'user',
                            'content' => $this->content
                        ]
                    ]
                ]);
            }
        }

        public function getFile(){
            if($this->dataType === 'ASR'){
                $this->file = curl_file_create($this->file);
            }
        }

        public function covert(){
            $apiUrl = $this->getApiUrl();
            $ch = curl_init($apiUrl);
            curl_setopt($ch, CURLOPT_POST, true);
            $this->getFile();
            curl_setopt($ch, CURLOPT_POSTFIELDS, $this->getData());
            curl_setopt($ch, CURLOPT_HTTPHEADER, $this->getHeader());
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($ch);
            curl_close($ch);

            if($response){
                return json_decode($response, true);
            }else{
                $this->error = "API REQUEST FAILED";
            }
        }

        public function upload($file){
            $fileTmp  = $file['tmp_name'];
            $filename = basename($file['name']);
            $fileSize = $file['size'];
            $errors   = $file['error'];
            $mime     = $file['type'];

            //get file extension
            $ext  = pathinfo($filename, PATHINFO_EXTENSION);
            $ext  = strtolower($ext);


            $parentDirectoy = dirname(dirname(dirname(__FILE__)));

            $allowedMedia = ['video/mp4','video/mpeg', 'audio/mpeg','audio/mpeg3','audio/wav'];

            if(in_array($mime, $allowedMedia)){
                if($fileSize <= 20000000){
                    $folder = 'files/';
                    $file   = $folder. md5(time() . mt_rand()) . '.'.$ext;
                    move_uploaded_file($fileTmp, $parentDirectoy . '/'.$file);
                    return $file;
                }else{
                    $this->error = "File is large!";
                }
            }else{
                $this->error = "invalid file format!";
            }
        }

        public function save($file, $content, $type){
            $stmt = $this->DB->prepare("INSERT INTO `files` (`fileUrl`, `content`, `type`) VALUES (:fileUrl, :content, :type)");
            $stmt->bindParam(":fileUrl", $file, PDO::PARAM_STR);
            $stmt->bindParam(":content", $content, PDO::PARAM_STR);
            $stmt->bindParam(":type", $type, PDO::PARAM_STR);
            $stmt->execute();
            return $this->DB->lastInsertId();
        }

        public function getRecentList(){
            $stmt = $this->DB->prepare("SELECT * FROM `files` ORDER BY `ID` DESC");
            $stmt->execute();
            $files = $stmt->fetchAll(PDO::FETCH_OBJ);

            foreach($files as $file){
                echo '<a href="view.php?file='.$file->ID.'">
                            <li class="rounded flex my-5 cursor-pointer hover:bg-gray-100 items-center">

                                <div class="w-14">
                                '.(($file->type === 'audio') ?
                                    '<img src="frontend/images/audio-img.png"/>' :
                                    '<img src="frontend/images/video-img.png"/>' )
                                .'
                                </div>
                                <div class=" overflow-hidden w-60 font-normal h-auto font-bold p-2 ">
                                    <div>
                                        <p class="truncate">'.$file->content.'</p>
                                    </div>
                                </div>

                            </li>
                        </a>';
            }
         }

        public function getFileById($fileID){
            $stmt = $this->DB->prepare("SELECT * FROM `files` WHERE `ID` = :fileID");
            $stmt->bindParam(":fileID", $fileID, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_OBJ);
        }

        public function update($fileID, $content, $lang){
            $stmt = $this->DB->prepare("UPDATE `files` SET `translated` = :content, `lang` = :lang WHERE `ID` = :fileID");
            $stmt->bindParam(":content", $content, PDO::PARAM_STR);
            $stmt->bindParam(":lang", $lang, PDO::PARAM_STR);
            $stmt->bindParam(":fileID", $fileID, PDO::PARAM_STR);
            $stmt->execute();
        }
    }
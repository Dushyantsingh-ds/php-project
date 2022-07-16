<form method="post" action="" enctype="multipart/form-data">
    <div>
        <input type="file" name="file">
    </div>
    <!--<div>
        <input type="file" name="files[]" multiple>
    </div>-->
    <select class="form-control" name="convertto">
        <option value="WORD">WORD</option>
        <option value="HTML">HTML</option>
        <option value="Excel">Excel</option>
    </select>
    <div>
        <button type="submit" name="submit">Submit</button>
    </div>
</form>
<?php

class Dadata
{
    public function __construct($apiKey, $post)
    {
        $this->apiKey = $apiKey;
        $this->convertto = $post["convertto"];
        if ($this->convertto === "WORD"){
            $this->extension = ".docx";
        }
        elseif ($this->convertto === "HTML"){
            $this->extension = ".html";
        }
        elseif ($this->convertto === "Excel"){
            $this->extension = ".xls";
        }
    }

    private function prepareRequest($curl, $data, $ContentType)
    {
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_HTTPHEADER,
            array('Content-Type: ' . $ContentType,
                'Accept: application/json',
                'Authorization: Token ' . $this->apiKey,
               ));
        curl_setopt($curl, CURLOPT_POST, 1);
        if ($ContentType == "multipart/form-data"){
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }
        elseif ($ContentType=="application/json"){
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
        }
    }

    private function executeRequest($url, $data, $ContentType)
    {
        $result = false;
        if ($curl = curl_init($url)) {
            $this->prepareRequest($curl, $data, $ContentType);
            $result = curl_exec($curl);
            $result = json_decode($result, true);
            curl_close($curl);
        }
        if ($result['data']== true){
            array_push($result, $data);
        }
        return $result;
    }
    private function RequestGetJob($url)
    {
        if ($curl = curl_init($url)) {
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($curl, CURLOPT_HTTPHEADER,
                array('Content-Type: application/json',
                    'Accept: application/json',
                    'Authorization: Token ' . $this->apiKey,
                    ));
            $result = curl_exec($curl);
            $result = json_decode($result, true);
            curl_close($curl);
            if ($result["data"]["status"]!== "Done"){
                return $this->RequestGetJob($url);
            }
            return $result;
        }
    }

    public function newobject($requestData)
    {
        $ContentType = "application/json";
        return $this->executeRequest("https://api.sautinsoft.com/api/v1/pdffocus/object/", $requestData, $ContentType);
        /*return $this->executeRequest("https://api.sautinsoft.com/api/v1/pdffocus/object/", $filename, $ContentType);*/
    }
    public function upload($result, $post, $existingKeys = '')
    {
        $id = $result['data']['id'];
        $url = "https://api.sautinsoft.com/api/v1/pdffocus/object/".$id."/file";
        $fields = array();
        $this->build_post_fields($post, $fields);
        $ContentType = "multipart/form-data";
        //$result = false;
        return $this->executeRequest($url, $fields, $ContentType);
    }

    private function JobRequest($url, $data, $ContentType)
    {

        $result = false;
        if ($curl = curl_init($url)) {
            $post = ['InputObjectId'=> $data,
                'Options'=>array ('direction' => $this->convertto,
                ),
                'Preset' => 'null',
                'Priority'=>0
            ];
            $this->prepareRequest($curl, $post, $ContentType);
            $result = curl_exec($curl);
            $result = json_decode($result, true);
            curl_close($curl);
        }
        return $result;

    }

    public function build_post_fields($data, &$returnArray, $existingKeys = '')
    {
        if (($data instanceof CURLFile) or !(is_array($data) or is_object($data))) {
            $returnArray[$existingKeys] = $data;
            return $returnArray;
        } else{
            foreach ($data as $key => $item) {
                $this->build_post_fields($item, $returnArray, ($existingKeys) ? $existingKeys . "[$key]" : $key);
            }
            return $returnArray;
        }
    }

    public function CreateJob($result, $post, $existingKeys = '')
    {
        $id = $result['data']['id'];

        $url = "https://api.sautinsoft.com/api/v1/pdffocus/";
        $ContentType = "application/json";

        return $this->JobRequest($url, $id, $ContentType);
    }

    public function StartJob($result, $post, $existingKeys = '')
    {
        $id = $result['data']['uniqId'];
        $url = "https://api.sautinsoft.com/api/v1/pdffocus/".$id."/start";
        return $this->executeRequest($url,$id,'');
    }
    public function GetJob($result, $post, $existingKeys = '')
    {
        $id = $result['0'];
        $url = "https://api.sautinsoft.com/api/v1/pdffocus/".$id;
        return $this->RequestGetJob($url);
    }

    public function Download($result, $post, $existingKeys = '')
    {
        $id = $result['data']['outputObjects'][0];
        $url = "https://api.sautinsoft.com/api/v1/pdffocus/object/".$id."/file";


        if ($curl = curl_init($url)) {
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($curl, CURLOPT_HTTPHEADER,
                array(
                    'Accept: application/json',
                    'Authorization: Token ' . $this->apiKey,
                    ));
            $result = curl_exec($curl);
            $file = preg_replace("/\.[^.]+$/", "", $post["name"]).$this->extension;
            file_put_contents($file, $result);
            if (file_exists($file)) {
                if (ob_get_level()) {
                    ob_end_clean();
                }
                header('Content-Description: File Transfer');
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename=' . basename($file));
                header('Content-Transfer-Encoding: binary');
                header('Expires: 0');
                header('Cache-Control: must-revalidate');
                header('Pragma: public');
                header('Content-Length: ' . filesize($file));
                readfile($file);
                exit;
            }
            unlink('1.docx');
            curl_close($curl);
            return $result;
        }
    }
}
if (isset($_POST['submit'])) {
    
    $post = $_POST;
    if (!empty($_FILES)) {
        foreach ($_FILES as $key => $row) {
            $diff = count($row) - count($row, COUNT_RECURSIVE);
            if ($diff == 0) {
                if(!empty($row['name']) && empty($row['error'])) {
                    $curl_file = new CURLFile($row['tmp_name'], $row['type'] , $row['name']);
                    $post = ["name" => $curl_file->getPostFilename(), "convertto"=>$post["convertto"]] ;
                    $post[$key] = $curl_file;
                }
            } else {
                $files = array();
                foreach($row as $k => $l) {
                    foreach($l as $i => $v) {
                        $files[$i][$k] = $v;
                    }
                }
                foreach ($files as $file) {
                    if(!empty($file['name']) && empty($file['error'])) {
                        $curl_file = new CURLFile($file['tmp_name'], $file['type'] , $file['name']);
                        $post[$key][] = $curl_file;
                    }
                }
            }
        }
    }

    $apiKey = "62a9b59bf2317b8cb62a9b59bf23256b52";
    $dadata = new Dadata($apiKey, $post);
    $result = $dadata->newobject($post);
    $result = $dadata->upload($result, $post);
    $result = $dadata->CreateJob($result, $post);
    $result = $dadata->StartJob($result, $post);
    $result = $dadata->GetJob($result, $post);
    $result = $dadata->Download($result, $post);
}
?>

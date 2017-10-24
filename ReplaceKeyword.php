<?php 
/* 
 *  功能：检索文章/文本中相应的关键词，对该关键词进行替换(增加a标签url)
 *  要求：1、同关键词只出现一次
 *        2、字符数长的链接优先
 *        3、同一篇文章不超过3个内链
 *  过程：1、载入word
 *        2、检索
 *        3、检索结果去重
 *        4、检索结果排序并切割
 *        5、对检索结果进行替换
 *        6、输出
*/


Class ReplaceKeyword
{

    // public function test(){
    //     $text = '一旦进去了，就再也见不到出来。我爷爷插的那根树枝又被淹没了，这说明水还在急涨。望着这浩浩荡荡的世界，也有些惶然';
    //     $wordArr = array();
    //     $wordArr[] = array('word'=>'进去', 'url'=>'<a href="http://www.bestkit.com/1">进去</a>');
    //     $wordArr[] = array('word'=>'出来', 'url'=>'<a href="http://www.bestkit.com/12">出来</a>');
    //     $wordArr[] = array('word'=>'淹没了', 'url'=>'<a href="http://www.bestkit.com/123">淹没了</a>');

    //     $wordObj = new ReplaceKeyword();
    //     $resText = $wordObj->transferWord2Url($text, $wordArr);

    //     dump($text);
    //     var_dump($resText);
    //     dump($resText);
    //     // 添加本地化存储即可
    // }
    
    const MAX_NUM = 3;
    const UTF8_BINARY_NUM = 3;
    const WORDURL_FILE_PATH = 'Public/keywords/word_url.dat';

    public $tree = array();


    public function transferWord2Url($text, $wordArr){
        if( empty($wordArr) ){
            return $text;
        }
        // 载入word
        $wordArr = $this->getWord2Arr($wordArr);
        // 检索
        $result = $this->search($text);
        if( empty($result) ){
            return $text;
        }
        // 去重
        $result = $this->uniqueMoreWord($result);
        if( count($result) > self::MAX_NUM ){
            $result = $this->removeMoreWord($result);
        }
        // dump($result);
        // 替换
        $fornum = count($result);
        $textLen = mb_strlen($text, 'utf8');
        $addOffset = 0;
        for($i=0;$i<$fornum;$i++){
            // 以UTF8编码进行处理
            $word = $result[$i]['word'];
            $replaceStr = $wordArr[$word];
            $replaceBeforeOffset = $result[$i]['first']+$addOffset;       // 偏移值添加上前面多出的部分
            $replaceAfterOffset = ($result[$i]['final']+1)+$addOffset;        // 偏移值添加上前面多出的部分
            // 替换
            $beforeText = mb_substr($text,0,$replaceBeforeOffset,'utf8');
            $afterText = mb_substr($text,$replaceAfterOffset,$textLen,'utf8');
            $text = $beforeText.$replaceStr.$afterText;
            // dump($beforeText);dump($replaceStr);dump($afterText);echo '<br/>';
            // 计算替换后多出来的字符量
            $addOffset += mb_strlen($replaceStr,'utf-8')-($result[$i]['final']-$result[$i]['first']+1);
        }
        // 输出
        return $text;
    }


    // 载入关键字池并返回
    protected function getWord2Arr($wordArr){
        $resArr = array();
        foreach($wordArr as $word){
            $this->insert(trim($word['word']));
            $resArr[$word['word']] = $word['url'];
        }
        return $resArr;
    }


    // 对匹配出关键词的结果进行去重
    protected function uniqueMoreWord($resultArr){
        $tempArr = array();
        foreach($resultArr as $rkey=>$result){
            // 保持索引键名一致
            $tempArr[$rkey] = $result['word'];
        }
        $tempArr = array_unique($tempArr);
        // 根据结果筛选
        if( count($tempArr)==count($resultArr) ){
            return $resultArr;
        } else {
            $resArr = array();
            foreach($tempArr as $resKey=>$temp){
                $resArr[$resKey] = $resultArr[$resKey];
            }
            // 返回
            return $resArr;
        }
    }


    // 当匹配出关键词数量大于上限时进行筛选
    protected function removeMoreWord($resultArr){
        $tempArr = array();
        // 按字符数大小逆序排序并切割
        foreach($resultArr as $rkey=>$result){
            $len = strlen($result['word']);
            $tempArr[$rkey] = $len;      // 保持键名一致，长度作为值
        }
        arsort($tempArr, SORT_NUMERIC);  // 逆序
        $tempArr = array_slice($tempArr, 0, self::MAX_NUM);
        // 对切割后的结果按在文章出现先后进行排序(重新索引)
        ksort($tempArr);                 // 正序
        // 根据结果筛选
        $resArr = array();
        foreach($tempArr as $tkey=>$temp){
            $resArr[] = $resultArr[$tkey];
        }
        // 返回
        return $resArr;
    }


    // 读取本地文件来获得关键词链接数据
    public function readFileData(){
        $fp = fopen(self::WORDURL_FILE_PATH, 'rb');
        $fileData = fgets($fp);
        fclose($fp);
        if( !empty($fileData) ){
            $resArr = unserialize($fileData);
            return $resArr;
        } else {
            return false;
        }
    }


    // 本地文件形式存储序列化后的文件
    public function saveFileData($wordArr){
        $fp = fopen(self::WORDURL_FILE_PATH, 'wb');
        if( $fp ){
            $resArr = array();
            foreach($wordArr as $word){
                $url = '<a href="'.$word['url'].'" target="_blank">'.$word['word'].'</a>';
                $resArr[] = array('word'=>$word['word'], 'url'=>$url);
            }
            $serializeRes = serialize($resArr);
            $res = fwrite($fp, $serializeRes);
            fclose($fp);
            if( $res ){
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }



    public static function get_chars($utf8_str){
        $s = $utf8_str;
        $len = strlen($s);
        if($len == 0) return array();
        $chars = array();
        for($i = 0;$i < $len;$i++){
            $c = $s[$i];
            $n = ord($c);
            if(($n >> 7) == 0){       //0xxx xxxx, asci, single
                $chars[] = $c;
            }
            else if(($n >> 4) == 15){     //1111 xxxx, first in four char
                if($i < $len - 3){
                    $chars[] = $c.$s[$i + 1].$s[$i + 2].$s[$i + 3];
                    $i += 3;
                }
            }
            else if(($n >> 5) == 7){  //111x xxxx, first in three char
                if($i < $len - 2){
                    $chars[] = $c.$s[$i + 1].$s[$i + 2];
                    $i += 2;
                }
            }
            else if(($n >> 6) == 3){  //11xx xxxx, first in two char
                if($i < $len - 1){
                    $chars[] = $c.$s[$i + 1];
                    $i++;
                }
            }
        }
        return $chars;
    }

  
    public function search($utf8_str){
        $resArr = array();
        $chars = $this->get_chars($utf8_str);
        $chars[] = null;
        $count = count($chars);
        $T = $this->tree;
        for($i=0;$i<$count;$i++){
            $tmpStr = '';
            $c = $chars[$i];
            if( array_key_exists($c, $T) ){     //存在则继续匹配
                $tmpStr .= $c;      //存储
                $T = $T[$c];        //取得子树
                for($j=$i+1;$j<$count;$j++){    //从文本下一字符开始查找
                    $remainc = $chars[$j];
                    if(array_key_exists($remainc, $T)) {    //匹配成功的时候
                        $tmpStr .= $remainc;    //继续存储
                        if( $T[$remainc] === array(NULL=>NULL) ){   //当剩余的子树是NULL时则结束该轮匹配
                            $resArr[] = array('word'=>$tmpStr,'first'=>$i,'final'=>$j);     //存储进结果中
                            $T = $this->tree;       //重新对T进行读取
                            break;
                        } elseif(array_key_exists(NULL, $T[$remainc])) {    //判定子树中是否有一分支为NULL，有则为匹配成功
                            $resArr[] = array('word'=>$tmpStr,'first'=>$i,'final'=>$j);
                            $T = $T[$remainc];
                            continue;
                        } else {        //当剩余的子树非NULL时则继续   
                            $T = $T[$remainc];
                            continue;
                        }
                    } else {
                        $T = $this->tree;   //对待测文本进行下一轮新的检测，重置关键字池
                        break;
                    }
                }
            } else {    //若无则continue；
                continue;
            }
        }
        return $resArr;
    }


    public function insert($utf8_str){
        $chars = $this->get_chars($utf8_str);
        $chars[] = null;    //串结尾字符
        $count = count($chars);
        $T = &$this->tree;
        for($i = 0;$i < $count;$i++){
            $c = $chars[$i];
            if(!array_key_exists($c, $T)){
                $T[$c] = array();   //插入新字符，关联数组
            }
            $T = &$T[$c];
        }
        $T = NULL;
    }
  

    public function remove($utf8_str){
        $chars = $this->get_chars($utf8_str);
        $chars[] = null;
        if($this->_find($chars)){    //先保证此串在树中
            $chars[] = null;
            $count = count($chars);
            $T = &$this->tree;
            for($i = 0;$i < $count;$i++){
                $c = $chars[$i];
                if(count($T[$c]) == 1){     //表明仅有此串
                    unset($T[$c]);
                    return;
                }
                $T = &$T[$c];
            }
        }
    }

  
    private function _find(&$chars){
        $count = count($chars);
        $T = &$this->tree;
        for($i = 0;$i < $count;$i++){
            $c = $chars[$i];
            if(!array_key_exists($c, $T)){
                return false;
            }
            $T = &$T[$c];
        }
        return true;
    }
  

    public function find($utf8_str){
        $chars = $this->get_chars($utf8_str);
        $chars[] = null;
        return $this->_find($chars);
    }
  
  
    public function contain($utf8_str, $do_count = 0){
        $chars = $this->get_chars($utf8_str);
        $chars[] = null;
        $len = count($chars);
        $Tree = &$this->tree;
        $count = 0;
        for($i = 0;$i < $len;$i++){
            $c = $chars[$i];
            if(array_key_exists($c, $Tree)){    //起始字符匹配
                $T = &$Tree[$c];
                for($j = $i + 1;$j < $len;$j++){
                    $c = $chars[$j];
                    if(array_key_exists(null, $T)){
                        if($do_count){
                            $count++;
                        }
                        else{
                            return true;
                        }
                    }
                    if(!array_key_exists($c, $T)){
                        break;
                    }
                    $T = &$T[$c];
                }
            }
        }
        if($do_count){
            return $count;
        }
        else{
            return false;
        }
    }

  
    public function contain_all($str_array){
        foreach($str_array as $str){
            if($this->contain($str)){
                return true;
            }
        }
        return false;
    }

  
    public function export(){
        return serialize($this->tree);
    }

  
    public function import($str){
        $this->tree = unserialize($str);
    }


}

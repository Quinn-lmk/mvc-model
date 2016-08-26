<?php
namespace Home\Model;
use Think\Model;

class DataModel extends Model{
    //初始化调用的函数，判断是否从微信服务器过来的
    //返回值为空
    public function checkWx(){
        $nonce     = $_GET['nonce'];
        $token     = '**lmk';
        $timestamp = $_GET['timestamp'];
        $echostr   = $_GET['echostr'];
        $signature = $_GET['signature'];
        //形成数组，然后按字典序排序
        $array = array($nonce, $timestamp, $token);
        sort($array);
        //拼接成字符串,sha1加密 ，然后与signature进行校验
        $str = sha1( implode( $array ) );
        if( $str  == $signature && $echostr ){
        //第一次接入weixin api接口的时候          
            echo  $echostr;
            exit;
        }else if($str  == $signature){
            //表明是从微信服务器过来的。
            //不执行任何操作
        }else{
            //不是从微信界面打开的时候
            echo "请从微信公众号打开";
            exit;
        }
    }
    //接收的消息为文本时的消息调用
    public function msgTemplate($content,$toUser,$fromUser){
        foreach ($content as $key => $value) {
            if($value['msgtype'] == 'text'){
                $content = $value['content'];
                $this->textTemplate($content,$toUser,$fromUser);
                exit;
            }else if($value['msgtype'] == 'news'){
                $this->newsTemplate($content,$toUser,$fromUser);
                exit;
            }else if($value['msgtype'] == 'image'){
                $this->imagesTemplate($content,$toUser,$fromUser);
                exit;
            }else{
                //不进行任何处理
            }
        }
    }
    //回复文本
    public function textTemplate($content,$toUser,$fromUser){
        $template = "<xml>
                    <ToUserName><![CDATA[%s]]></ToUserName>
                    <FromUserName><![CDATA[%s]]></FromUserName>
                    <CreateTime>%s</CreateTime>
                    <MsgType><![CDATA[%s]]></MsgType>
                    <Content><![CDATA[%s]]></Content>
                    </xml>";
        $time = time();
        $msgType = 'text';
        echo sprintf($template,$toUser,$fromUser,$time,$msgType,$content);
    }

    //回复图文
    public function newsTemplate($content,$toUser,$fromUser){
        $time = time();
        //定义一个变量来存储图文消息
        $template ="<xml>
                    <ToUserName><![CDATA[%s]]></ToUserName>
                    <FromUserName><![CDATA[%s]]></FromUserName>
                    <CreateTime>%s</CreateTime>
                    <MsgType><![CDATA[news]]></MsgType>
                    <ArticleCount>".count($content)."</ArticleCount>
                    <Articles>";
        foreach($content as $key=>$value){
            $template.="<item>
                      <Title><![CDATA[".$value['title']."]]></Title> 
                      <Description><![CDATA[".$value['description']."]]></Description>
                      <PicUrl><![CDATA[".$value['picurl']."]]></PicUrl>
                      <Url><![CDATA[".$value['url']."]]></Url>
                      </item>";
        }

        $template.="</Articles></xml>";
        var_dump(sprintf($template,$toUser,$fromUser,$time));
        echo sprintf($template,$toUser,$fromUser,$time);
    }
    //用户发来图片处理
    //核心模块
    //核心模块
    //核心模块
    //核心模块
    //核心模块
    public function imagesToCode($postObj,$toUser,$fromUser){
        /** 这里所有的改动必须与流程图一起改动
        *   这里所有的改动必须与流程图一起改动
        *   这里所有的改动必须与流程图一起改动
        */
        $user=M('user');
        $detail =M('detail');
        //图片本身的二维码
        $picUrlImg = $postObj->PicUrl;
        //两种方式识图二维码，如果错误查询数据库
        //成功得到链接，失败返回false
        $picUrl = $this->wweiImgToUrl($picUrlImg,$toUser,$fromUser);
        if($picUrl){
        } else{
            $picUrl = $this->imgToUrl($picUrlImg,$toUser,$fromUser);
            if($picUrl){
            }else{
                $content ='服务器繁忙，请稍后再试';
                $this->textTemplate($content,$toUser,$fromUser);
                exit;
            }
        }
        //得到openid的查询think_user，判断该用户已经上传的个数
        $numUrlCount['openid'] =$toUser;
        $res = $user->where($numUrlCount)->select();
        $num = count($res);
        //判断该用户已经上传的图片个数。由think_openid得到该用户可以上传的个数
            $openid = M('openid');
            $numItem = $openid->where($numUrlCount)->getField('num_item');
            if($numItem<10){
                $content ='生成的条目异常，已通知客服，24小时内解决';
                $this->textTemplate($content,$toUser,$fromUser);
                //数据写入think_tome
                $tome = M('tome');
                $toMeArr['from'] = $toUser;
                $toMeArr['time'] = date("Y-m-d H:i:s",time());
                $toMeArr['other'] = '生成条目异常';
                $tome->add($toMeArr);
                exit;
            }
        //如果数字大于40，那么接入支付
        if($num>30){
            $content ='你已经生成30个了，如果还想更多，请升级VIP,回复“VIP”购买，如有';
            $this->textTemplate($content,$toUser,$fromUser);exit;
        }
        if($num>$numItem){
            $content ='目前你的二维码图片已经超过了'.$numItem."个，创建失败,想要生成更多，点击或回复    '>更多</a>\n点击或回复"."<a href='#".$toUser."'>二维码</a>查看你的页面";
            $this->textTemplate($content,$toUser,$fromUser);exit;
        }
        //在think_user表中查询url的id，如果id存在，表示该url已经传过了
        $numUrlCount['url'] = strval($picUrl);
        $urlid = $user->where($numUrlCount)->getField('id');
        if($urlid){
            $content ="这个二维码你已经传过了\n图片ID：".$urlid."\n修改内容请回复“".$urlid."内容”例如回复:\n“".$urlid."”";
            $this->textTemplate($content,$toUser,$fromUser);
            exit;
        }

        //根据host查询think_detail表中的数据，更新comment、status数据。

        $tempu=parse_url($picUrl);  
        $host=$tempu['host'];
        //在liunx**下，上面这两句代码可以失败，如果失败，那么host只取链接中的8到18个字符
        if($host ==null){
            $host = substr($picUrl,8,10);
        }
        //如果host为微信，特殊处理
        if($host == 'weixin.qq.com'){
            //由于该**链接只能在微信端打开，故不支持该**，请换一个试试
            $content ="由于该链接只能在微信端打开，故不支持该**，请换一个试试\n\n回复或点击<a href=''>**</a>查看支持的**\n\n";
            $this->textTemplate($content,$toUser,$fromUser);
            //将这个链接写入数据库，后期调用
            $wx = M('wx');
            $errorInfo['url'] =$picUrl ;
            $errorInfo['host'] = $host;
            $errorInfo['time'] = date("Y-m-d H:i:s",time());
            $errorInfo['openid'] = $toUser;
            $wx->add($errorInfo);
            //
            exit;
        }
        $conditionDetail['host'] = strval($host);
        $detailRes = $detail->where($conditionDetail)->select();
        //如果数量为0，表示我还没有下载这个软件
        if(count($detailRes) == 0){
            $content = '这个APP小编还没有收集，请24小时后再试';
            $this->textTemplate($content,$toUser,$fromUser);
            $tome = M('tome');
            //判断host是否存在
            $conditionInTome['host'] = $host;
            $res = $tome->where($conditionInTome)->getField('id');
            if($res){
                //host存在
                $condition['id'] = $res;
                 $tome->where($condition)->setInc('repeat_num',1);
                // $repeat = $tome->where($conditionInTome)->getField('repeat');
                // $repeat = $repeat +1 ;
                // $conditionhost['id']= $res;
                // $conditionhost['repeat']= $repeat;
                // $tome->save($conditionhost);
            }else{
                //host 不存在
                $toMeArr['from'] = $toUser;
                $toMeArr['time'] = date("Y-m-d H:i:s",time());
                $toMeArr['url'] = strval($picUrl);
                $toMeArr['other'] = '我没有这个APP';
                $toMeArr['host'] = $host;
                $toMeArr['repeat_num'] = '1';
                $tome->add($toMeArr);
            }
            exit;
        }
        //将图片中的链接生成一个新的地址
        //将图片中的链接生成一个新的地址
        //得到生成的图片链接 两种方式
        $codeAddress = $this->wweiUrltoImg($picUrl,$toUser);
        if(!$codeAddress){
            $codeAddress = $this->baiDuUrlToCode($picUrl,$toUser);
        }
        if(!$codeAddress){
            $content ='服务器繁忙，请稍后再试';
            $this->textTemplate($content,$toUser,$fromUser);
            exit;
        }

        //将生成的图片链接保存到我的服务器中，命名规则
        $filePrefix = strval(date("Ymd",time()));//使用年月日来保存文件，因此存的文件可能很多
        $name =  md5(uniqid(microtime(true),true)); //生成唯一文件名
        $codeAddress = $this->saveImg($codeAddress,$filePrefix,$name);


        //写入url，因为后面写coment和status时要根据这个url来写
        $map['openid'] = $toUser; 
        $map['url'] = strval($picUrl); 
        $map['code_address'] = strval($codeAddress); 
        $map['host'] = strval($host); 
        //写入think_user是否成功
        $result =$user->add($map);
        if(!$result){
            $content ='保存失败，请稍后再试';
            $this->textTemplate($content,$toUser,$fromUser);
            $error = M('error');
            $errorInfo['error_info'] = 'url,host,openid 写入数据库think_user失败';
            $errorInfo['from'] = '图片处理模块';
            $errorInfo['time'] = date("Y-m-d H:i:s",time());
            $errorInfo['openid'] = $toUser;
            $error->add($errorInfo);
            //删除添加的信息
            $this->deleteUserItemAndImg($result,$filePrefix,$name,$codeAddress);
            exit;
        }
        //遍历从think_detail中得的信息，根据host查询detail中的content与icon_address，再结合前面将url写入user表时得到的id，将结果写入think_user中。
        foreach ($detailRes as $key => $value) {
            if($value['status'] == 'ios'){
                $conditionUser['icon_address'] = $value['iconaddress'];
                $conditionUser['content_ios'] = $value['content'];
                $outInfoIos= "**：ap\n**：“".$value['content']."”\n"; //为输给用户的信息
                $conditionUser['id'] = $result;
                $conditionUser['time'] = time();
                //写入数据表中
                if(!($user->save($conditionUser))){
                    $this->textTemplate('ios保存失败',$toUser,$fromUser);
                    //删除添加的信息
                    $this->deleteUserItemAndImg($result,$filePrefix,$name,$codeAddress);
                    exit;
                }
            }else if($value['status'] == 'andriod'){
                $conditionUser['icon_address'] = $value['iconaddress'];
                $conditionUser['content_andriod'] = $value['content'];
                $outInfoAndriod= "**：**\n**：“".$value['content']."”\n"; //为输给用户的信息
                $conditionUser['id'] = $result;
                $conditionUser['time'] = time();
                if(!($user->save($conditionUser))){
                    $this->textTemplate('andriod保存失败',$toUser,$fromUser);
                    //删除添加的信息
                    $this->deleteUserItemAndImg($result,$filePrefix,$name,$codeAddress);
                    exit;
                }
                //var_dump($conditionUser);exit;
            }else if($value['status'] == 'share'){
                $conditionUser['icon_address'] = $value['iconaddress'];
                $conditionUser['content_share'] = $value['content'];
                $outInfoShare= "**：**\n**：“".$value['content']."”\n"; //为输给用户的信息
                $conditionUser['id'] = $result;
                $conditionUser['time'] = time();
                if(!($user->save($conditionUser))){
                    $this->textTemplate('share保存失败',$toUser,$fromUser);
                    //删除添加的信息
                    $this->deleteUserItemAndImg($result,$filePrefix,$name,$codeAddress);
                    exit;
                }
            }else{
                $content = '表detail中host中status不属性ios andriod share，请';
                $this->textTemplate($content,$toUser,$fromUser);
                //删除添加的信息
                $this->deleteUserItemAndImg($result,$filePrefix,$name,$codeAddress);
                exit;
            }
            $conditionUser = '';
        }
        //回复给用户信息
        $content = "保存成功，图片ID：".$result."\n".$outInfoIos.$outInfoAndriod.$outInfoShare."\n如果想修改内容，增加yqm等，回复或点击<a href='http://youhost/View/helpNewer.html'>教程</a>\n\n点击“<a href='http://youhost/View/custom/index.html?u=".$toUser."'>我的**</a>”查看你的**\n回复或点击<a href='http://aaaa/dkadf'>**</a>查看别人的**\n回复“二维码”生成你的专属页面";
        $this->textTemplate($content,$toUser,$fromUser);
        exit;
    }
    public function deleteUserItemAndImg($result,$filePrefix,$name,$codeAddress){
        //删除数据库中信息
        $user =M('user');
        $condition['id']=$result;
        $user->where($condition)->delete();
        //删除存在本地的图片
        //得到后缀
        $exp =explode('.', $codeAddress);
        $exp =$exp[count($exp)-1];
        $dir = '/data/home/**/**/**/User/'.$filePrefix.'/'.$name.'.'.$exp;
        @unlink($dir); 
    }
    //核心模块结束
    //用户请求修改信息//“123-ap-内容”这种模式
    public function modifyInfo($postObjContent,$toUser,$fromUser){
        //处理postObjContent
        $arrAll=array();
        $arrAll[0]='ap';
        $arrAll[1]='**';
        $arrAll[2]='**';
        $arr=explode('-', $postObjContent);
        //将内容以‘ - ’拆分成数组。并判断数组前两个元素是否符合“123-ap-”
        if(!(is_numeric($arr[0]))){
            return false;
        }
        if(!(in_array($arr[1], $arrAll))) {
            return false;
        }
        $condition = null;
        switch ($arr[1]) {
            case 'ap':
                $str = 'content_ios';
                break;
            case '**':
                $str = 'content_andriod';
                break;
            case '**':
                $str = 'content_share';
                break;
            default:
                $str ='';
                break;
        }
        $condition['id'] = $arr[0];
        $id['id'] = $arr[0];
        $condition['status'] = $arr[1];
        $condition[$str] = $arr[2];
        $user=M('user');
        //判断该id是否属性该用户的openid下
        $openid = $user->where($id)->getField('openid');
        if($openid!=$toUser){
            $content = 'ID错误，请检查数字是否正确，回复“列表”查看你的ID列表';
            $this->textTemplate($content,$toUser,$fromUser);
            exit;
        }
        $user->save($condition);
        $content ="更新成功\n".'图片ID:'.$arr[0]."\n"."**：\n".$arr[2];
        $this->textTemplate($content,$toUser,$fromUser);
        exit;
    
    }
    //“微信-微信帐号”这种模式
    public function createWeixin($postObjContent,$toUser,$fromUser){
        $arr=explode('-', $postObjContent);
        if($arr[0] != '微信'){
            return false;
        }
        $openid = M('openid');
        $condition['openid'] = $toUser;
        $conditionsave['**'] = strval($arr[1]);
        $openid->where($condition)->save($conditionsave);
        $content = '微信：'.$arr[1].' 保存成功';
        $this->textTemplate($content,$toUser,$fromUser);
        exit;
    }
    
    //“删除-NUM” 这种模式
    public function deleteItem($postObjContent,$toUser,$fromUser){
        $arr=explode('-', $postObjContent);
        if(!is_numeric($arr[0])){
            return false;
        }
        if($arr[1] != '删除'){
            return false;
        }
        $user = M('user');
        $condition['id'] = $arr[0];
        $id['id'] = $arr[0];
        $openid = $user->where($id)->getField('openid');
        if($openid!=$toUser){
            $content = 'ID错误，请检查数字是否正确，回复“列表”查看你的ID列表';
            $this->textTemplate($content,$toUser,$fromUser);
            exit;
        }
        $user->where($condition)->delete();
        $content = 'ID:'.$arr[0].' 删除成功';
        $this->textTemplate($content,$toUser,$fromUser);
        exit;
    }
    //“ID-yqm-NUM"这种模式，让用户增加yqm
    public function addInvitation($postObjContent,$toUser,$fromUser){
        $arr=explode('-', $postObjContent);
        if(!is_numeric($arr[0])){
            return false;
        }
        if($arr[1] != 'yqm'){
            return false;
        }

        $user = M('user');
        $id['id'] = $arr[0];
        $openid = $user->where($id)->getField('openid');
        if($openid!=$toUser){
            $content = 'ID错误，请检查数字是否正确，回复“列表”查看你的ID列表';
            $this->textTemplate($content,$toUser,$fromUser);
            exit;
        }
        $condition['id'] = $arr[0];
        $condition['code_invitation'] = $arr[2];
        if($user->save($condition)){
            $content = 'ID:'.$arr[0].' yqm增加成功';
            $this->textTemplate($content,$toUser,$fromUser);
            exit;
        }else{
            $content = 'ID:'.$arr[0].' yqm增加失败，请联系客服';
            $this->textTemplate($content,$toUser,$fromUser);
            exit;
        }
    }
    //生成个人xc的二维码链接
    public function creatCode($postObjContent,$toUser,$fromUser){
        $openid=M('openid');
        $condition['openid'] = $toUser;
        //先判断数据库中是否有这个信息，如果有，就直接返回
        if($openid->where($condition)->getField('invitation_url')){
            $content =$openid->where($condition)->getField('invitation_url');
            $content = "<a href='".$content."'>二维码</a>";
            $this->textTemplate($content,$toUser,$fromUser);
            exit;
        }
        //如果没有，执行下面的程序
        $url = 'http://youhost/View/custom/index.html?u='.$toUser;
        //将链接生成图片，调用上面的方法
        $codeAddress = $this->wweiUrltoImg($url,$toUser);
        if(!$codeAddress){
            $codeAddress = $this->baiDuUrlToCode($url,$toUser);
        }
        if(!$codeAddress){
            $content ='服务器繁忙，请稍后再试';
            $this->textTemplate($content,$toUser,$fromUser);
            exit;
        }
        //后期使用微信的短链接，现在先使用955cc的api
        $shortUrl = file_get_contents("http://aaaa/short/?url=".$url."&format=json");
        $arr = json_decode($shortUrl,1);
        if($arr['errno'] == 0){ //等于0表示成功
            $myShortUrl = $arr['url'];
        }else{
            $content ='生成链接服务繁忙，请稍后再试';
            $this->textTemplate($content,$toUser,$fromUser);
            exit;
        }
        //将生成的个人链接图片保存在本地
        $imgAddress = $this->saveImgForCreatCode($codeAddress,$toUser);
        $str = 'http://youhost/View/showUserCode.html?imgurl='.$imgAddress.'&myShortUrl='.$myShortUrl;
        //将个人xc链接写入openId中
        $condition2['invitation_url'] = strval($str);
        $openid->where($condition)->save($condition2);
        //返回链接
        $content = $str;
        $content = "<a href='".$content."'>二维码</a>";
        $this->textTemplate($content,$toUser,$fromUser);
        exit;
    }
    ////得到用户个人信息(后期改成图文信息)
    public function userInfo($toUser,$fromUser){
        $user=M('user');
        $openid = M('openid');
        $condition['openid'] = $toUser;
        //查询两张表，并将结果合并到一个数组中
        $resOpenid = $openid->where($condition)->select();
        $resUser = $user->where($condition)->order('id asc')->getField('id,content_ios,content_andriod,content_share');
        //两个数组合并成一个
        $res=array_merge($resOpenid,$resUser);
        $iosNum =1;
        $andriodNum =1;
        $shareNum =1;
        foreach ($res as $key => $value) {
            if($value['content_ios'] != null){
                $ios .= 'sw**'.$iosNum.'：图片ID:'.$value['id']."\n";
                $iosNum = $iosNum + 1;
            }
            if($value['content_andriod'] != null){
                $andriod .= 'sw**'.$andriodNum.'：图片ID:'.$value['id']."\n";
                $andriodNum = $andriodNum + 1;
            }
            if($value['content_share'] != null){
                $share .= 'sw**'.$shareNum.'：图片ID:'.$value['id']."\n";
                $shareNum = $shareNum + 1;
            }
        }
        //如果没有**和**，则为空

        $ios = substr($ios,0,strlen($ios)-1)."\n"; 
        $andriod = substr($andriod,0,strlen($andriod)-1)."\n"; 
        $share = substr($share,0,strlen($share)-1)."\n"; 
        $str .='微信：'.$res[0]['**']."\n";
        $str .='能生成的条目数：'.$res[0]['num_item']."\n\n";
        $str .='**：ap入口'."\n";
        $str .="图片ID（与<a href='http://youhost/View/custom/index.html?u=".$toUser."'>你的**</a>对应） \n".$ios."\n";
        $str .="**：**入口"."\n";
        $str .="图片ID: \n".$andriod."\n";
        $str .="**：**转发"."\n";
        $str .="图片ID：\n".$share."\n";
        $str .="<a href='http://youhost/View/helpNewer.html'>点我查看教程</a>\n";
        $str .="<a href='http://youhost/View/custom/index.html?u=".$toUser."'>查看我的**</a>";

        $content = $str;
        $this->textTemplate($content,$toUser,$fromUser);
        exit;
    }
    //查看最近十条错误消息
    //功能：根据数字，1开头回复详细信息，2开头只回复重要的信息，前8位类型必须是133330
    public function showErrorInfo($num,$toUser,$fromUser){
        if(substr($num,0,8)!='133330' && substr($num,0,8)!='233330'){
            return false;
        }
        if(!is_numeric($num)){return false;}
        $str="错误信息如下：\n";
        $flag=$num;
        $num = intval(substr($num, -3));
        $error=M('error');
        $res = $error->field('from,time,error_info')->order('id desc')->limit($num)->select();
        if(substr($flag,0,1)==1){
            foreach ($res as $key => $value) {
                $value['time']=substr($value['time'],8,8);
                $str .= $value['time'].'-'.$value['from'].'-'.$value['error_info'].";\n";
            }
        }else{
            foreach ($res as $key => $value) {
                $str .= $value['from'].";\n";
            }
        }
        $content = $str;
        $this->textTemplate($content,$toUser,$fromUser);
        exit;
    }
    ////133330tomenum得到我要下载的链接
    public function getUrlToMe($num,$toUser,$fromUser){
        if(substr($num,0,8)=='333330' || substr($num,0,8)=='433330' ||substr($num,0,8)=='533330'){
            //不进行任何操作
        }else{
            return false;
        }
        if(!is_numeric($num)){return false;}
        $flag=$num;
        $num = intval(substr($num, -3));
        $str="getUrlTome：\n";
        $tome=M('tome');
        $res = $tome->field('time,id,url,other')->order('repeat_num desc')->limit($num)->select();
        if(substr($flag,0,1)==3){
            foreach ($res as $key => $value) {
                $value['time']=substr($value['time'],8,8);
                $str .= $value['time'].'-'.$value['id'].'-'.$value['other'].";\n";
            }
        }else if(substr($flag,0,1)==4){
            foreach ($res as $key => $value) {
                $str .= $value['url'].";\n\n";
            }
        }else if(substr($flag,0,1)==5){
            //执行删除操作
            if($tome->where('id='.$num)->delete()){
                $content = 'id='.$num.'删除成功';
                $this->textTemplate($content,$toUser,$fromUser);
                exit;
            }else{
                $content = 'id='.$num.'删除失败';
                $this->textTemplate($content,$toUser,$fromUser);
                exit;
            }
            

        }else{
            $content = '删除getUrlTome异常';
            $this->textTemplate($content,$toUser,$fromUser);
            exit;
        }
        $content = $str;
        $this->textTemplate($content,$toUser,$fromUser);
        exit;
    }
    //百度 api store 识别图中二维码
    //识别正确返回一个URL，错误写入数据库中
    //第一个参数为图片地址，第二个参数为用户名。
    function imgToUrl($imgUrl,$toUser,$fromUser){
        $ch = curl_init();
        $url = 'http://apis.baidu.com/showapi_open_bus/code2/code2_rec?imgUrl='.$imgUrl;
        $header = array(
            'apikey:youkey',
        );
        // 添加apikey到header
        curl_setopt($ch, CURLOPT_HTTPHEADER  , $header);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        // 执行HTTP请求
        curl_setopt($ch , CURLOPT_URL , $url);
        $res = curl_exec($ch);
        curl_close($ch);
        $arr = json_decode($res,ture);
            /*来源：http://apistore.baidu.com/apiworks/servicedetail/2773.html
            "showapi_res_code": 0, //系统级结果，0为成功，其他失败
            "showapi_res_error": "",//失败时的中文提示
            "showapi_res_body": {
                "ret_code": "0",//0为业务级成功，其他失败
                "flag": "true",//操作结果的布尔标识
                "msg": "操作成功!",//结果的中文提示
                "retText": "https://www.showapi.com" //识别结果
            }
            */
        if($arr['showapi_res_code'] == 0 && $arr['showapi_res_body']['ret_code'] == 0){
            //返回URL值
            return $arr['showapi_res_body']['retText'];
        }else{
            //错误信息入库
            $error = M('error');
            $errorInfo['error_info'] = strval($arr['showapi_res_error'].' '.$arr['msg']);
            $errorInfo['from'] = '百度api 识别二维码';
            $errorInfo['time'] = date("Y-m-d H:i:s",time());
            $errorInfo['openid'] = $toUser;
            $error->add($errorInfo);
            return false;
        }
    }
    //图片转成链接 weiwei 
    function wweiImgToUrl($imgUrl,$toUser,$fromUser){
        //语法实例：http://api.wwei.cn/dewwei.html?data=http://www.wwei.cn/Uploads/qrcode/2014/10/22/5447b8ba1c877.png&apikey=youkeys
        //$this->textTemplate('faeee',$toUser,$fromUser);
       $ch = curl_init();
       //由于这个imgUrl中不包含图片的后缀，wwei会识别失败，调用一个函数将这个链接图片保存到本地生成一个链接再次发送。
       //这里生成了文件，以后要删除这个文件
       $filename = md5(uniqid(microtime(true),true));//文件名与api相同时，同一请求认为是相同的;
       $imgUrl = $this->saveImgFromUrl($imgUrl,$filename);
       $url = 'http://api.wwei.cn/dewwei.html?data='.$imgUrl."&apikey=youkeys";
       curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
       curl_setopt($ch, CURLOPT_URL, $url);
       curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
       $output = curl_exec($ch);
       curl_close($ch);
       //得到的信息转成数组
       $arr = json_decode($output,true);

        //删除文件，路径来源于保存图片时使用的绝对位置
         $dir = '/data/home/**/**/**/Temp/'.$filename.'.jpg';
         @unlink($dir); 
        if($arr['status'] == 1){
            return $arr['data']['raw_text'];
        }else{
            $wwerror = M('error');
            $wwerrorInfo['error_info'] = strval($arr['msg']);
            $wwerrorInfo['from'] = 'wwei 识别二维码';
            $wwerrorInfo['time'] = date("Y-m-d H:i:s",time());
            $wwerrorInfo['openid'] = $toUser;
            $wwerror->add($wwerrorInfo);
            return false;
        }
    }
    //链接转成图片,返回是图片地址
    function wweiUrltoImg($inputUrl,$toUser){
        //请求实例 http://api.wwei.cn/wwei.html?data=http%3A%2F%2Fwww.wwei.cn%2F&version=1.0&apikey=youkeys
        //测试链接：http://***//11.jpg
        $ch = curl_init();
        //$inputUrl= 'http://api2.ppyaoqing.cn/app/?inviter=1483262';
        //$inputUrl= 'youhost';
        $url = 'http://api.wwei.cn/wwei.html?data='.$inputUrl."&version=1.0&apikey=youkeys";
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($ch);
        curl_close($ch);
        //得到的信息转成数组
        $arr = json_decode($output,true);
        if($arr['status'] == 1){
            //成功，返回生成的图片地址
            return $arr['data']['qr_filepath'];
            
        }else{
            //图片生成二维码失败
            $wwerror = M('error');
            $wwerrorInfo['error_info'] = strval($arr['msg']);
            $wwerrorInfo['from'] = 'wwei生成图片失败';
            $wwerrorInfo['time'] = date("Y-m-d H:i:s",time());
            $wwerrorInfo['openid'] = $toUser;
            $wwerror->add($wwerrorInfo);
            return false;
        }
    }
    /**链接转成图片,返回是图片地址
    *来源：http://apistore.baidu.com/apiworks/servicedetail/2773.html*/
    function baiDuUrlToCode($inputUrl,$toUser){
        //$inputUrl ='http://api2.ppyaoqing.cn/app/?inviter=1483262';     //要生成的链接
        $size = 10;        //图片大小 整数类型:1-10 ,图片大小为:67+12*(size-1). 如size为1时,图片的像素为67px * 67px。
        $imgExtName ='jpg';  //图片格式:jpeg,jpg,png或者gif
        $ch = curl_init();
        $url = 'http://apis.baidu.com/showapi_open_bus/code2/code2_make?content='.$inputUrl.'&size='.$size.'&imgExtName='.$imgExtName;
        $header = array(
            'apikey:youkey',
        );
        curl_setopt($ch, CURLOPT_HTTPHEADER  , $header);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch , CURLOPT_URL , $url);
        $res = curl_exec($ch);
        curl_close($ch);
        $result = json_decode($res,true);
        if($result['showapi_res_code'] == 0 && $result['showapi_res_body']['ret_code'] == 0){
            return $result['showapi_res_body']['imgUrl'];
        }else{
            $error = M('error');
            $errorInfo['error_info'] = strval($arr['showapi_res_error'].' '.$arr['msg']);
            $errorInfo['from'] = 'baidu生成图片失败';
            $errorInfo['time'] = date("Y-m-d H:i:s",time());
            $errorInfo['openid'] = $toUser;
            $error->add($errorInfo);
            return false;
        }
    }
    /** 从链接中保存文件,这是为了WWEI二维码识别做的一个中转,产生的临时文件*/
    protected function saveImgFromUrl($url,$name){
        //本地测试中是不支持的https请求的，不过放心在服务器上是可以运行的~不错不错
        //$testhttp ='http://***//11.jpg';
        //$testhttps ='https://youhost/img/bd_logo1.png';
        $img = file_get_contents($url); 
        //file中文件必须要使用绝对路径
        $dir = '/data/home/**/**/**/Temp/'.$name.'.jpg';
        file_put_contents($dir,$img); 
        return 'http://youhost/Temp/'.$name.'.jpg'; 
    }
    //个人xc时图片的储存位置,每个人都有一张二维码
    protected function saveImgForCreatCode($url,$name){
        //本地测试中是不支持的https请求的，不过放心在服务器上是可以运行的~不错不错
        //$testhttp ='http://***//11.jpg';
        //$testhttps ='https://youhost/img/bd_logo1.png';
        $img = file_get_contents($url); 
        $exp =explode('.', $url);
        $exp =$exp[count($exp)-1];
        //file中文件必须要使用绝对路径
        $dir = '/data/home/**/**/**/User/userCode/'.$name.'.'.$exp;
        file_put_contents($dir,$img); 
        return 'http://youhost/User/userCode/'.$name.'.'.$exp;
    }
    //链接生成图片时，图片的储存位置,  
    public function saveImg($url,$filePrefix,$name){
        //本地测试中是不支持的https请求的，不过放心在服务器上是可以运行的~不错不错
        //$testhttp ='http://***//11.jpg';
        //$testhttps ='https://youhost/img/bd_logo1.png';
        $img = file_get_contents($url); 
        //file中文件必须要使用绝对路径
        //判断文件路径是否存在，不存在就创建一个
        $path = '/data/home/**/**/**/User/'.$filePrefix;
        if (!file_exists($path)){
            mkdir($path,0777,true);
            chmod($path,0777);
        }
        //url中含有图片的后缀
        $exp =explode('.', $url);
        $exp =$exp[count($exp)-1];
        $dir = $path.'/'.$name.'.'.$exp;
        file_put_contents($dir,$img); 
        return 'http://youhost/User/'.$filePrefix.'/'.$name.'.'.$exp;
    }
    //处理detail这张数据表，得到链接的标题~
    public function detailGetTitle($url){
        //使用curl这个神奇的东西
        $c = curl_init();
        curl_setopt($c, CURLOPT_URL, $url);
        curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
        $data = curl_exec($c);
        curl_close($c);
        $pos = strpos($data,'utf-8');
        if($pos===false){$data = iconv("gbk","utf-8",$data);}
        preg_match("/<title>(.*)<\/title>/i",$data, $title);
        return $title[1];
    }
}
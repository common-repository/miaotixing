<?php
function miaotixing_load(){
    //获得配置 miaotixing_option
    $option = miaotixing_get_option();
    //查看要监控的钩子类型
    $hook = miaotixing_get_array_value_by_key($option, 'hook', []);
    //钩子：新用户注册
    $hook_user_signup = miaotixing_get_array_value_by_key($hook, 'user_signup', 0);
    if($hook_user_signup === 1) { add_action( 'register_post','miaotixing_hook_user_signup', 999, 3 ); }
    //钩子：评论发表
    $hook_comment_post = miaotixing_get_array_value_by_key($hook, 'comment_post', 0);
    if($hook_comment_post === 1) { add_action( 'comment_post','miaotixing_hook_comment_post', 999, 3 ); }
}

/**
 * 获得设置
 */
function miaotixing_get_option(){
    $option = get_option('miaotixing_option');
    if(empty($option)) return [];
    return json_decode($option, true);
}
/**
 * 写入设置
 */
function miaotixing_update_option($option){
    return update_option('miaotixing_option', json_encode($option));
}


/**
 * 从数组内获得参数
 */
function miaotixing_get_array_value_by_key($array, $key, $defalutValue=null){
    if(array_key_exists($key, $array)) return $array[$key];
    return $defalutValue;
}

/**
 * 各种钩子类型hook
 */

/** hook:新用户注册 */
function miaotixing_hook_user_signup( $sanitized_user_login, $user_email, $errors){
    if(empty($errors->errors)){
        miaotixing_send('新用户注册：' . $user_email);
    }
}
/** hook:用户发表评论 */
function miaotixing_hook_comment_post($comment_ID, $comment_approved, $commentdata){
    if($comment_approved === 0 || $comment_approved === 1){
        $comment_content = $commentdata['comment_content'];
        if(mb_strlen($comment_content) > 20) $comment_content = mb_substr( $comment_content, 0, 20, 'utf-8' );
        miaotixing_send('新评论(文章id-' . $commentdata['comment_post_ID'] . ')：' . $comment_content);
    }
}


/**
 * 执行推送
 */
function miaotixing_send($text){
    $option = miaotixing_get_option();
    //喵码
    $miao_code = miaotixing_get_array_value_by_key($option, 'miao_code', null);
    //下次发送时间
    $nextsend_timestamp = miaotixing_get_array_value_by_key($option, 'nextsend_timestamp', 0);
    //当前时间戳
    $now_timestamp = time();
    $now_timestring = date('H:i:s', $now_timestamp);
    //缓存的提醒内容
    $text_buffer = miaotixing_get_array_value_by_key($option, 'text_buffer', []);

    $text = $now_timestring . ' ' . $text;
    $text_buffer[] = $text;
    $text = join(PHP_EOL ,$text_buffer);
    $option['text_buffer'] = $text_buffer;
    
    if($now_timestamp > $nextsend_timestamp){
        $jsonObj = miaotixing_send_function($miao_code, $text);
        
        if ($jsonObj->code === 0) {
            //发送成功，更新下次发送时间
            $option['nextsend_timestamp'] = time() + 61;
            $option['text_buffer'] = [];
        }
        else{
            //如果发送失败，记录下最后一条失败记录
            $option['last_error'] = $now_timestring . ' 发送失败，错误代码：' . $jsonObj->code . '，描述：' . $jsonObj->msg;
        }
    }
    else {
        //距离上次发送不到1分钟，设置定时器
        $cron_send_timestamp = wp_next_scheduled('miaotixing_cron_send_event');
        if($cron_send_timestamp === false){
            wp_schedule_single_event($nextsend_timestamp, 'miaotixing_cron_send_event' );
        }
    }
    miaotixing_update_option($option);
}
/**
 * 外部调用：发送提醒
 */
function miaotixing($text){
    return miaotixing_send($text);
}

/**
 * 定时推送
 */
function miaotixing_cron_send(){
    $option = miaotixing_get_option();
    //喵码
    $miao_code = miaotixing_get_array_value_by_key($option, 'miao_code', null);
    //缓存的提醒内容
    $text_buffer = miaotixing_get_array_value_by_key($option, 'text_buffer', []);
    $text = join(PHP_EOL ,$text_buffer);
    if(!empty($text)){
        $jsonObj = miaotixing_send_function($miao_code, $text);
        
        if(!empty($jsonObj)) {
            if ($jsonObj->code === 0) {
                //发送成功，更新下次发送时间
                $option['nextsend_timestamp'] = time() + 61;
                $option['text_buffer'] = [];
            }
            else{
                //如果发送失败，记录下最后一条失败记录
                $option['last_error'] = $now_timestring . ' 发送失败，错误代码：' . $jsonObj->code . '，描述：' . $jsonObj->msg;
            }
        }
        else {
            //未知错误
            $option['last_error'] = $now_timestring . ' 发送失败，错误代码：未知';
        }
        miaotixing_update_option($option);
    }
}
add_action( 'miaotixing_cron_send_event','miaotixing_cron_send' );


/**
 * 推送请求发送
 */
function miaotixing_send_function($miao_code, $text){
    $response = wp_remote_get('http://miaotixing.com/trigger?type=json&id=' . urlencode($miao_code) . '&text=' . urlencode($text));
    if ( is_array( $response ) && ! is_wp_error( $response ) ) {
        return json_decode($response['body'], false);
    }
    else {
        return null;
    }
}
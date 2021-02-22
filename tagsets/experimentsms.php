<?php
    require 'awssns/autoload.php';

    use Aws\Sns\SnsClient;
    use Aws\Exception\AwsException;

function experimentsms_get_configuration(){
    ///we return and object with all de information of the sms configuration
    $sms_options=array();
    $pars=array(':type'=>'sms');
    $query="select * from ".table('options')."
        where option_type= :type
        order by option_name";
    $result=or_query($query,$pars);
    while($sms_line=pdo_fetch_assoc($result)){
        $sms_options[$sms_line['option_name']]=$sms_line['option_value'];
    }
    return $sms_options;
}

function experimentsms_sms_topics(){

    $sms_options=experimentsms_get_configuration();

    $SnSclient = new SnSclient([
        'version'     => $sms_options['sms_version'],
        'region'      => $sms_options['sms_region'],
        'credentials' => [
            'key'    => $sms_options['sms_aws_key_id'],
            'secret' => $sms_options['sms_aws_key_secret'],
        ],
    ]);
    return $SnSclient->listTopics();
}

function experimentsms_sms($smsmessage, $phonenumber){
    ///sent sms throught amazon sns service

    $sms_options=experimentsms_get_configuration();

    if ($sms_options['sms_enable']=='on'){

        $SnSclient = new SnSclient([
            'version'     => $sms_options['sms_version'],
            'region'      => $sms_options['sms_region'],
            'credentials' => [
                'key'    => $sms_options['sms_aws_key_id'],
                'secret' => $sms_options['sms_aws_key_secret'],
            ],
        ]);


        $message = $smsmessage;
        $phone = '+'.$sms_options['country_code'].$phonenumber;

        try {
            $result = $SnSclient->publish([
                'Message' => $message,
                'PhoneNumber' => $phone,
            ]);
            return $result;
        } catch (AwsException $e) {
            // output error message if fails
            error_log($e->getMessage(),0);
        }
    }

}

function experimentsms_confirmation_sms($participant){
    global $settings__root_url, $settings;
    ///sent message for confirmation sms
    $message='Buen día, por favor confirmar su cuenta para recibir invitaciones a experimentos en el siguiente enlace: '.$settings__root_url."/public/participant_confirm.php?c=".urlencode($participant['confirmation_token']);
    experimentsms_sms($message, $participant['phone_number']);
}

function experimentsms__send_invitations_to_queue($experiment_id,$whom="not-invited") {
    switch ($whom) {
        case "not-invited":     $aquery=" AND invited=0 "; break;
        case "all":             $aquery=""; break;
        default:                $aquery=" AND ".table('participants').".participant_id='0' ";
    }
    mt_srand((double)microtime()*1000000);
    $order="ORDER BY rand(".mt_rand().") ";
    $now=time();
    $status_query=participant_status__get_pquery_snippet("eligible_for_experiments");
    $pars=array(':experiment_id'=>$experiment_id);
    $query="INSERT INTO ".table('sms_queue')." (timestamp,sms_type,sms_recipient,experiment_id)
            SELECT ".$now.",'invitation', ".table('participants').".participant_id, experiment_id
            FROM ".table('participants').", ".table('participate_at')."
            WHERE experiment_id= :experiment_id
            AND ".table('participants').".participant_id=".table('participate_at').".participant_id ".
            $aquery."
            AND session_id = '0' AND pstatus_id = '0'";
    if ($status_query) $query.=" AND ".$status_query;
    $query.=" ".$order;
    $done=or_query($query,$pars);
    $count=pdo_num_rows($done);
    return $count;
}

function load_sms($mail_name,$lang) {
    global $authdata;
    $pars=array(':mail_name'=>$mail_name);
    $query="SELECT * FROM ".table('lang')."
            WHERE content_type='sms'
            AND content_name= :mail_name";
    $marr=orsee_query($query,$pars);
    if (isset($marr[$lang])) {
        $mailtext=$marr[$lang];
    } elseif (isset($authdata['language'])) {
        $mailtext=$marr[$authdata['language']];
    } elseif (isset($marr['en'])) {
        $mailtext=$marr['en'];
    } else {
        $mailtext='';
    }
    return $mailtext;
}

function experimentsms__sms_in_queue($type="",$experiment_id="",$session_id="") {
    $pars=array();
    if ($type) {
        $tquery=" AND sms_type= :type ";
        $pars[':type']=$type;
    } else $tquery="";
    if ($experiment_id) {
        $equery=" AND experiment_id= :experiment_id ";
        $pars[':experiment_id']=$experiment_id;
    } else $equery="";
    if ($session_id) {
        $squery=" AND session_id= :session_id ";
        $pars[':session_id']=$session_id;
    } else $squery="";
    $query="SELECT count(sms_id) as number FROM ".table('sms_queue')."
            WHERE sms_id>0 ".$tquery.$equery.$squery;
    $line=orsee_query($query,$pars);
    $number=$line['number'];
    return $number;
}

?>
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

function experimentsms_invitation_sms($participant){

}


?>
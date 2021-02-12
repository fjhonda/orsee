<?php
    error_log('entre_sms',0);
    require 'awssns/autoload.php';

    use Aws\Sns\SnsClient;
    use Aws\Exception\AwsException;

function experimentsms_sms($smsmessage, $phonenumber){
    ///sent sms throught amazon sns service

    $sms_options=array();
    $pars=array(':type'=>'sms');
    $query="select * from ".table('options')."
        where option_type= :type
        order by option_name";
    $result=or_query($query,$pars);
    while($sms_line=pdo_fetch_assoc($result)){
        $sms_options[$sms_line['option_name']]=$sms_line['option_value'];
    }

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
            var_dump($result);
        } catch (AwsException $e) {
            // output error message if fails
            error_log($e->getMessage());
        }
    }

}


?>
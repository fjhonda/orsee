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

function experimentsms_sms_test($smsmessage, $phonenumber){
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
            return true;
        } catch (AwsException $e) {
            // output error message if fails
            error_log($e->getMessage(),0);
            return false;
        }
    }

}

function experimentsms_sms($smsmessage, $phonenumber){
    ///write in log a test of sending a message for reduce sms costs

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
            $txt="Test mensaje de texto. Message sent: ".$message.' - Destination: '. $phonenumber. ' Time:'. date("Y-m-d H:i:s");
            file_put_contents('/home/francis/logs.txt', $txt.PHP_EOL , FILE_APPEND | LOCK_EX);
            return true;
        } catch (AwsException $e) {
            // output error message if fails
            error_log($e->getMessage(),0);
            return false;
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
            AND ".table('participants').".no_email<>''
            AND ".table('participants').".participant_id=".table('participate_at').".participant_id ".
            $aquery."
            AND session_id = '0' AND pstatus_id = '0'";
    if ($status_query) $query.=" AND ".$status_query;
    $query.=" ".$order;
    $done=or_query($query,$pars);
    $count=pdo_num_rows($done);
    return $count;
}

function load_sms($sms_name,$lang) {
    global $authdata;
    $pars=array(':mail_name'=>$sms_name);
    $query="SELECT * FROM ".table('lang')."
            WHERE content_type='sms'
            AND content_name= :mail_name";
    $marr=orsee_query($query,$pars);
    if (isset($marr[$lang])) {
        $smstext=$marr[$lang];
    } elseif (isset($authdata['language'])) {
        $smstext=$marr[$authdata['language']];
    } elseif (isset($marr['en'])) {
        $smstext=$marr['en'];
    } else {
        $smstext='';
    }
    return $smstext;
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

function experimentsms__send_sms_from_queue($number=0,$type="",$experiment_id="",$session_id="") {
    global $settings;

    $pars=array();
    if ($number>0) {
        $limit=" LIMIT :number ";
        $pars[':number']=$number;
    } else $limit="";
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

    $ssms=array(); $ssms_ids=array();
    $invitations=array(); $reminders=array(); $bulks=array(); $warnings=array();
    $errors=array();
    $reminder_text=array(); $warning_text=array(); $inv_texts=array();
    $exps=array(); $sesss=array(); $parts=array(); $labs=array();
    $pform_fields=array();
    $slists=array();

    // first get mails to send
    $query="SELECT * FROM ".table('sms_queue')."
            WHERE error = '' ".
            $tquery.$equery.$squery."
            ORDER BY timestamp, sms_id ".
            $limit;

    $result=or_query($query,$pars);
    while ($line=pdo_fetch_assoc($result)) {
        $ssms[]=$line;
        $ssms_ids[]=$line['sms_id'];
    }

    // so we don't handle errors at all, and just delete here?!?
    //$pars=array();
    //foreach ($ssms_ids as $id) $pars[]=array(':id'=>$id);
    //$query="DELETE FROM ".table('mail_queue')."
    //      WHERE mail_id = :id";
    //$done=or_query($query,$pars);

    foreach ($ssms as $line) {
        $texp=$line['experiment_id'];
        $tsess=$line['session_id'];
        $tpart=$line['sms_recipient'];
        $ttype=$line['sms_type'];
        $tbulk=$line['bulk_id'];
        $continue=true;

        // well, if experiment_id, session_id, recipient, footer or inv_text, add to array
        if (!isset($exps[$texp]) && $texp)  $exps[$texp]=orsee_db_load_array("experiments",$texp,"experiment_id");
        if (!isset($sesss[$tsess]) && $tsess) $sesss[$tsess]=orsee_db_load_array("sessions",$tsess,"session_id");
        if (!isset($parts[$tpart]) && $tpart) $parts[$tpart]=orsee_db_load_array("participants",$tpart,"participant_id");
        $tlang=$parts[$tpart]['language'];
        //if (!isset($footers[$tlang])) $footers[$tlang]=load_mail("public_mail_footer",$tlang);
        /*if ($ttype=="session_reminder" && !isset($reminder_text[$texp][$tlang])) {
            $smstext=false;
            if ($settings['enable_session_reminder_customization']=='y')
                $smstext=experimentmail__get_customized_mailtext('experiment_session_reminder_mail',$texp,$tlang);
            if (!isset($smstext) || !$smstext || !is_array($smstext)) {
                $smstext['subject']=load_language_symbol('email_session_reminder_subject',$tlang);
                $smstext['body']=load_mail("public_session_reminder",$tlang);
            }
            $reminder_text[$texp][$tlang]=$smstext;
        }*/
        /*if ($ttype=="noshow_warning" && !isset($warning_text[$tlang])) {
            $warning_text[$tlang]['text']=load_mail("public_noshow_warning",$tlang);
            $warning_text[$tlang]['subject']=load_language_symbol('email_noshow_warning_subject',$tlang);
        }*/
        /*if (($ttype=="session_reminder" || $ttype=="noshow_warning") && !isset($labs[$tsess][$tlang])) {
            $labs[$tsess][$tlang]=laboratories__get_laboratory_text($sesss[$tsess]['laboratory_id'],$tlang);
        }*/


        if ($ttype=="invitation" && !isset($inv_texts[$texp][$tlang]))
            $inv_texts[$texp][$tlang]=experimentsms__load_invitation_text($texp,$tlang);
        if ($ttype=="invitation" && !isset($slists[$texp][$tlang]))
            $slists[$texp][$tlang]=experimentmail__get_session_list($texp,$tlang);

        /*if ($ttype=="bulk_mail" && !isset($bulk_mails[$tbulk][$tlang]))
                        $bulk_mails[$tbulk][$tlang]=experimentmail__load_bulk_mail($tbulk,$tlang);
*/
        // check for missing values ...
        if (!isset($parts[$tpart]['participant_id'])) {
            $continue=false;
            // email error: no recipient
            $line['error'].="no_recipient:";
        } else {
            if (!isset($pform_fields[$tlang])) $pform_fields[$tlang]=participant__load_participant_email_fields($tlang);
            $parts[$tpart]=experimentmail__fill_participant_details($parts[$tpart],$pform_fields[$tlang]);
        }

        if (!isset($exps[$texp]['experiment_id']) && ($ttype=="invitation" || $ttype=="session_reminder" || $ttype=="noshow_warning")) {
            $continue=false;
            // email error: no experiment id given
            $line['error'].="no_experiment:";
        }

        if (!isset($sesss[$tsess]['session_id']) && ($ttype=="session_reminder" || $ttype=="noshow_warning")) {
            $continue=false;
            // email error: no session id given
            $line['error'].="no_session:";
        }

        if (!isset($inv_texts[$texp][$tlang]) && $ttype=="invitation") {
            $continue=false;
            // email error: no inv_text given
            $line['error'].="no_inv_text:";
        }

        if (!isset($bulk_mails[$tbulk][$tlang]) && $ttype=="bulk_mail") {
            $continue=false;
            // email error: no bulk_mail given
            $line['error'].="no_bulk_mail_text:";
        }

        // fine, if no errors, add to arrays
        if ($continue) {
            switch ($line['sms_type']) {
                case "invitation":
                    $invitations[]=$line;
                    break;
                case "session_reminder":
                    $reminders[]=$line;
                    break;
                case "noshow_warning":
                    $warnings[]=$line;
                    break;
                case "bulk_mail":
                    $bulks[]=$line;
                    break;
            }
        } else {
            $errors[]=$line;
        }
    }

    // fine now we have everything we want, and we can proceed with sending the mails

    $sms_sent=0; $sms_errors=0; $invsms_not_sent=0;

    // reminders
    /*foreach ($reminders as $sms) {
        $tlang=$parts[$sms['mail_recipient']]['language'];
        $done=experimentmail__send_session_reminder_mail($sms,$parts[$sms['mail_recipient']],
        $exps[$sms['experiment_id']],$sesss[$sms['session_id']],
        $reminder_text[$sms['experiment_id']][$tlang],$labs[$sms['session_id']][$tlang],
        $footers[$tlang]);
        if ($done) {
            $sms_sent++;
            $deleted=experimentmail__delete_from_queue($sms['mail_id']);
        } else {
            $sms['error']="sending";
            $errors[]=$sms;
        }
    }*/

    // noshow warnings
    /*foreach ($warnings as $sms) {
        $tlang=$parts[$sms['mail_recipient']]['language'];
        $done=experimentmail__send_noshow_warning_mail($sms,$parts[$sms['mail_recipient']],
        $exps[$sms['experiment_id']],$sesss[$sms['session_id']],
        $warning_text[$tlang],$labs[$sms['session_id']][$tlang],
        $footers[$tlang]);
        if ($done) {
            $sms_sent++;
            $deleted=experimentmail__delete_from_queue($sms['mail_id']);
        } else {
            $sms['error']="sending";
            $errors[]=$sms;
        }
    }*/

    // invitations
    foreach ($invitations as $sms) {
        $tlang=$parts[$sms['sms_recipient']]['language'];
        if ($exps[$sms['experiment_id']]['experiment_type']=='laboratory' && (!trim($slists[$sms['experiment_id']][$tlang]))) {
            $done=true; // do not send invitation when session_list is empty!
            $invsms_not_sent++;
        } else {
            $done=experimentsms__send_invitation_sms($sms,$parts[$sms['sms_recipient']],
            $exps[$sms['experiment_id']],$inv_texts[$sms['experiment_id']][$tlang],
            $slists[$sms['experiment_id']][$tlang],$footers[$tlang]);
            if ($done) $sms_sent++;
        }
        if ($done) {
            $deleted=experimentsms__delete_from_queue($sms['sms_id']);
        } else {
            $sms['error']="sending";
            $errors[]=$sms;
        }
    }

    // bulks
    /*foreach ($bulks as $sms) {
        $tlang=$parts[$sms['mail_recipient']]['language'];
        $done=experimentmail__send_bulk_mail($sms,$parts[$sms['mail_recipient']],$bulk_mails[$sms['bulk_id']][$tlang],$footers[$tlang]);
        if ($done) {
            $sms_sent++;
            $deleted=experimentmail__delete_from_queue($sms['mail_id']);
        } else {
            $sms['error']="sending";
            $errors[]=$sms;
        }
    }
    $done=experimentmail__gc_bulk_mail_texts();*/

    // handle errors
    $pars=array(); $sms_errors=count($errors);
    if ($sms_errors>0) {
        foreach ($errors as $sms) $pars[]=array(':error'=>$sms['error'],':sms_id'=>$sms['sms_id']);
        $query="UPDATE ".table('sms_queue')."
                SET error= :error
                WHERE sms_id= :sms_id";
        $done=or_query($query,$pars);
    }
    $mess['mails_sent']=$sms_sent;
    $mess['mails_invmails_not_sent']=$invsms_not_sent;
    $mess['mails_errors']=$sms_errors;
    return $mess;
}

function experimentsms__delete_from_queue($sms_id) {
    $pars=array(':sms_id'=>$sms_id);
    $query="DELETE FROM ".table('sms_queue')."
            WHERE sms_id= :sms_id";
    $result=or_query($query,$pars);
    return $result;
}

    function experimentsms__load_invitation_text($experiment_id,$tlang="") {
        global $settings;
        if (!$tlang) $tlang=$settings['public_standard_language'];
        $pars=array(':experiment_id'=>$experiment_id);
        $query="SELECT * from ".table('lang')."
                WHERE content_type='experiment_invitation_sms'
                AND content_name= :experiment_id";
        $experiment_sms=orsee_query($query,$pars);
        return $experiment_sms[$tlang];
    }


    function experimentsms__send_invitation_sms($sms,$part,$exp,$inv_text,$slist,$footer) {
        global $settings;
        $part=experimentsms__get_invitation_sms_details($part,$exp,$slist);
        // split in subject and text
        $subject=stripslashes(str_replace(strstr($inv_text,"\n"),"",$inv_text));
        $smstext=stripslashes(substr($inv_text,strpos($inv_text,"\n")+1,strlen($inv_text)));
        $recipient=$part['phone_number'];
        $message=process_sms_template($smstext,$part)."\n".process_sms_template($footer,$part);
        $sender=experimentsms__get_sender_sms($exp);
        //$headers="From: ".$sender."\r\n";
        $done=experimentsms__sms($recipient,$message);
        $done2=experimentsms__update_invited_flag($sms);
        return $done;
    }

    function experimentsms__update_invited_flag($sms) {
        $pars=array(':participant_id'=>$sms['sms_recipient'],
                    ':experiment_id'=>$sms['experiment_id']);
        $query="UPDATE ".table('participate_at')."
                SET invited=1
                WHERE participant_id= :participant_id
                AND experiment_id= :experiment_id";
        $result=or_query($query,$pars);
        return $result;
    }

    function experimentsms__sms($recipient,$message) {
        global $settings;
        //$headers .= "Content-Type: text/plain; charset=\"UTF-8\"\r\n";
        //$message=html_entity_decode($message,ENT_COMPAT,'UTF-8');
        //$subject=html_entity_decode($subject,ENT_COMPAT,'UTF-8');
        //$subject='=?UTF-8?B?'.base64_encode($subject).'?=';
        $done = experimentsms_sms($message, $recipient);
        return $done;
    }

    function experimentsms__get_sender_sms($experiment) {
        global $settings;
        if ($settings['enable_editing_of_experiment_sender_email']=='y' && $experiment['sender_mail'])
            return $experiment['sender_mail'];
        else return $settings['support_mail'];
    }

    function experimentsms__get_invitation_sms_details($part,$exp,$slist) {
        global $settings;
        $part['edit_link']='';//experimentmail__build_edit_link($part);
        $part['enrolment_link']=experimentsms__build_lab_registration_link($part);
        $part['experiment_name']=$exp['experiment_public_name'];
        $part['sessionlist']=$slist;
        $part['link']=experimentsms__build_lab_registration_link($part);
        $part['public_experiment_note']=$exp['public_experiment_note'];
        $part['ethics_by']=$exp['ethics_by'];
        $part['ethics_number']=$exp['ethics_number'];
        return $part;
    }


    function experimentsms__build_lab_registration_link($participant) {
        global $settings__root_url, $settings;
        if (isset($settings['subject_authentication']) && $settings['subject_authentication']=='username_password') $token_string='';
        else $token_string="?p=".urlencode($participant['participant_id_crypt']);
        $reg_link=$settings__root_url."/public/participant_show.php".$token_string;
        return $reg_link;
    }

    function process_sms_template($template,$vararray) {
        $output=explode("\n",$template);
        $vars=array_keys($vararray);
        foreach ($vars as $key) {
            $i=0;
            foreach ($output as $outputline) {
                $output[$i]=str_replace("#".$key."#",$vararray[$key],$output[$i]);
                $i++;
            }
        }
        $result="";
        foreach($output as $outputline) $result=$result.trim($outputline)."\n";
        return $result;
    }

?>
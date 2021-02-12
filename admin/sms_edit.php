<?php

ob_start();
$title="config_sms";
$jquery=array('switchy','datepicker');
$menu_area="options";

include('header.php');
include('../config/system.php');

experimentsms_sms('Prueba desde ORSEE', '59753536');

$sms_options=array();

if ($proceed){

    $pars=array(':type'=>'sms');
    $query="select * from ".table('options')."
        where option_type= :type
        order by option_name";
    $result=or_query($query,$pars);
    while($sms_line=pdo_fetch_assoc($result)){
        $sms_options[$sms_line['option_name']]=$sms_line['option_value'];
    }

}

if (isset($_REQUEST['change'])){
    //we process the action to save the sms config
    //creating parameters

    $pars_new=array();
    $pars_update=array();
    $now=time();
    //validate if the parameter exists
    if (count($sms_options)>0){
        //we do the update procedure
        $options_keys=array_keys($sms_options);
        foreach ($options_keys as $o){
            $pars_update[]=array(':value'=>$_REQUEST[$o],
                                ':name'=>$o,
                                ':type'=>'sms');
        }
    }
    else {
        //we insert the options for the first time
        $options_keys=array('sms_enable','sms_version','sms_region','sms_aws_key_id','sms_aws_key_secret', 'country_code');
        foreach($options_keys as $o){
            $pars_new[]=array(':value'=>$_REQUEST[$o],
                                ':name'=>$o,
                                ':type'=>'sms',
                                ':now'=>$now);
            $now++;
        }
    }


    //we insert or update data
    if (count($pars_update)>0){
        //we update values
        $query="UPDATE ".table('options')."
                SET option_value= :value
                WHERE option_name= :name
                AND option_type= :type";
        try{
            $done=or_query($query, $pars_update);
        }
        catch(Exception $e){
            echo print_r($e);
        }
    }
    if (count($pars_new)>0){
        //we insert values
        $query="INSERT INTO ".table('options')." SET
                option_id= :now,
                option_name= :name,
                option_value= :value,
                option_type= :type";

        $done=or_query($query, $pars_new);
    }

    message(lang('changes_saved'));
    //log_admin("options_edit","type:sms");
    redirect('admin/sms_edit.php');
}

//Section of the page properly
echo '<form action="sms_edit.php" method=post>';

if ($proceed){


    echo '   <TABLE class="or_formtable" style="margin: 0 auto;">';
    echo '
    <TR>
        <TD colspan=2 align=center>
            <INPUT class="button" type=submit name="change" value="'.lang('change').'">
        </TD>
    </TR>
    <TR><TD colspan=2><hr></TD></TR>';
    echo '
    <TR>
        <TD>
            '.lang('enable_sms').'
        </TD>
        <TD align=center>';
            echo '<INPUT type="radio" name="sms_enable" '.($sms_options['sms_enable']=='on'?'checked': '').' value="on"> '. lang('sms_enabled');
            echo '<INPUT type="radio" name="sms_enable" '.($sms_options['sms_enable']!='on'?'checked': '').' value="off"> '.lang('sms_disabled');
        echo '</TD>
    </TR>';
    echo '
    <TR>
        <TD>
            '.lang('sms_version').'
        </TD>
        <TD align=center>
            <INPUT type="text" name="sms_version" size="20" maxlength="100" value="'.$sms_options['sms_version'].'">
        </TD>
    </TR>';
    echo '
    <TR>
        <TD>
            '.lang('sms_region').'
        </TD>
        <TD align=center>
            <INPUT type="text" name="sms_region" size="20" maxlength="100" value="'.$sms_options['sms_region'].'">
        </TD>
    </TR>';
    echo '
    <TR>
        <TD>
            '.lang('sms_aws_key_id').'
        </TD>
        <TD align=center>
            <INPUT type="text" name="sms_aws_key_id" size="20" maxlength="100" value="'.$sms_options['sms_aws_key_id'].'">
        </TD>
    </TR>';
    echo '
    <TR>
        <TD>
            '.lang('sms_aws_key_secret').'
        </TD>
        <TD align=center>
            <INPUT type="text" name="sms_aws_key_secret" size="20" maxlength="100" value="'.$sms_options['sms_aws_key_secret'].'">
        </TD>
    </TR>';
    echo '
    <TR>
        <TD>
            '.lang('country_code').'
        </TD>
        <TD align=center>
            <INPUT type="text" name="country_code" size="20" maxlength="100" value="'.$sms_options['country_code'].'">
        </TD>
    </TR>';
    echo '
    <TR><TD colspan=2><hr></TD></TR>
    <TR>
        <TD colspan=2 align=center>
            <INPUT class="button" type=submit name="change" value="'.lang('change').'">
        </TD>
    </TR>';

    echo '</TABLE>';
}


echo '</form>';

include("footer.php");

?>
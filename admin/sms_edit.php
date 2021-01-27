<?php

ob_start();
$title="config_sms";
$jquery=array('switchy','datepicker');
$menu_area="options";

include('header.php');
include('../config/system.php');

$sms_options=array();

if ($proceed){

    $opts=$system_options_sms;

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
    foreach ($opts as $o){
        if (isset($sms_options[$o['option_name']])){
            //is for update
            $pars_update[]=array(':value'=>$_REQUEST[$o['option_name']],
                                ':name'=>$o['option_name'],
                                ':type'=>'sms');
        }
        else{
            //is for insert
            $pars_new[]=array(':value'=>$_REQUEST[$o['option_name']],
                            ':name'=>$o['option_name'],
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
            echo print_r($pars_update);
            $done=or_query($query, $pars_update);
        }
        catch(Exception $e){
            echo print_r($e);
        }
    }
    if (count($pars_new)>0){
        echo 'conteo insert';
        //we insert values
        $query="INSERT INTO ".table('options')." SET
                option_id= :now,
                option_name= :name,
                option_value= :value,
                option_type= :type";

        $done=or_query($query, $pars_new);
        echo $done;
    }

    message(lang('changes_saved'));
    log_admin("options_edit","type:sms");
    redirect('admin/sms_edit.php');
}

//Section of the page properly
echo '<form action="sms_edit.php" method=post>';

if ($proceed){
    if (check_allow('settings_edit')) echo '
    <FORM action="sms_edit.php" method=post>';

    echo '   <TABLE class="or_formtable">';
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
            echo '<INPUT type="radio" name="sms_enable"> '. lang('sms_enabled');
            echo '<INPUT type="radio" name="sms_enable"> '.lang('sms_disabled');
        echo '</TD>
    </TR>';
    echo '
    <TR>
        <TD>
            '.lang('sms_version').'
        </TD>
        <TD align=center>
            <INPUT type="text" name="sms_version" size="20" maxlength="100" value="">
        </TD>
    </TR>';
    echo '
    <TR>
        <TD>
            '.lang('sms_region').'
        </TD>
        <TD align=center>
            <INPUT type="text" name="sms_region" size="20" maxlength="100" value="">
        </TD>
    </TR>';
    echo '
    <TR>
        <TD>
            '.lang('sms_aws_key_id').'
        </TD>
        <TD align=center>
            <INPUT type="text" name="sms_key_id" size="20" maxlength="100" value="">
        </TD>
    </TR>';
    echo '
    <TR>
        <TD>
            '.lang('sms_aws_key_secret').'
        </TD>
        <TD align=center>
            <INPUT type="text" name="sms_key_secret" size="20" maxlength="100" value="">
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
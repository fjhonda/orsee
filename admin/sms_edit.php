<?php

ob_start();
$title="config_sms";
$menu_area="options";
include('header.php');

if (isset($_REQUEST['change'])){
    echo 'logre editar';
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
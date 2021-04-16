<?php
// part of orsee. see orsee.org
ob_start();
$jquery=array('popup');
$title="registered_but_not_confirmed_xxx";
$menu__area="participants";
include ("header.php");
if ($proceed) {
    $allow=check_allow('participants_unconfirmed_edit','participants_main.php');
}

if ($proceed) {
    if (isset($_REQUEST['deleteall']) && $_REQUEST['deleteall']) $dall=true; else $dall=false;
    if (isset($_REQUEST['deletesel']) && $_REQUEST['deletesel']) $dsel=true; else $dsel=false;
    if (isset($_REQUEST['sendreconfirmation']) && $_REQUEST['sendreconfirmation'] && isset($_SESSION['plist_ids'])) $reconfirmation_mail=true; else $reconfirmation_mail=false;

    if ($reconfirmation_mail){
        $mails_sent=experimentmail__send_reconfirmations_to_queue($_SESSION['plist_ids']);
        message ($mails_sent.' '.lang('reconfirmation_mails_sent'));
        redirect ("admin/participants_unconfirmed.php");
    }

    if ( $dall || $dsel ) {

        $ok=false;
        if ($dsel) {
            $dids=array();
            if (isset($_REQUEST['sel']) && is_array($_REQUEST['sel'])) {
                foreach($_REQUEST['sel'] as $k=>$v) {
                    if ($v=='y') $dids[]=$k;
                }
            }
            if (count($dids)>0) {
                $ok=true;
                $i=0; $pars=array(); $parnames=array();
                foreach ($dids as $id) {
                    $i++;
                    $pars[':participant_id'.$i]=$id;
                    $parnames[]=':participant_id'.$i;
                }
                $in_clause=" AND participant_id IN (".implode(",",$parnames).")";
            }
        } elseif ($dall) {
            $ok=true;
            $pars=array();
            $in_clause="";
        }

        if ($ok) {
            $query="SELECT participant_id, email
                    FROM ".table('participants')."
                    WHERE status_id='0' ".$in_clause;
            $result=or_query($query,$pars);
            while ($line=pdo_fetch_assoc($result)) $del_emails[$line['participant_id']]=$line['email'];

            $query="DELETE FROM ".table('participants')."
                    WHERE status_id='0' ".$in_clause;
            $done=or_query($query,$pars);
            $number=pdo_num_rows($done);

            message ($number.' '.lang('xxx_temp_participants_deleted'));
            foreach ($del_emails as $participant_id=>$email) {
                log__admin("participant_unconfirmed_delete","participant_id: ".$participant_id.', email: '.$email);
            }
            redirect ("admin/participants_unconfirmed.php");
        }
    }
}

if ($proceed) {
    echo '<center>';

    echo '<FORM action="participants_unconfirmed.php" method="POST">';

        $posted_query=array('query'=> array(0=> array("statusids_multiselect"=>array("not"=>"", "ms_status"=>"0"))));
        $query_array=query__get_query_array($posted_query['query']);
        $query=query__get_query($query_array,0,array(),'creation_time DESC',false);

    echo '<BR>
        <TABLE width="80%" border="0">
        <TR><TD>
            <TABLE width="100%" border="0">
            <TR><TD width="50%" align="right">
            <input class="button" type=submit name="deleteall" value="'.lang('delete_all').'">
            </TD><TD width="50%" align="right">
            <input class="button" type=submit name="deletesel" value="'.lang('delete_selected').'">
            </TD></TR>
            </TABLE>
        </TD></TR>
        <TR><TD colspan="2">';

    $pids=query_show_query_result($query,"participants_unconfirmed",false);

    $_SESSION['plist_ids']=$pids;

    echo '</FORM>';
    echo '</TD></TR></TABLE>';

    //$emailstring=implode(",",$emails);
        echo '<BR><BR><input class="button" type="submit" name="sendreconfirmation" value="'.lang('write_message_to_all_listed').'" />';
    echo '<BR><BR>'.button_link('participants_main.php',lang('back'),'level-up').'<BR><BR>';

    echo '</CENTER>';

}
include ("footer.php");
?>
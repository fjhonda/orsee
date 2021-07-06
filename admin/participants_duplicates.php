<?php
// part of orsee. see orsee.org
ob_start();

$menu__area="participants";
$title="search_for_duplicates";
$jquery=array('popup');
include ("header.php");
if ($proceed) {
    $allow=check_allow('participants_duplicates','participants_main.php');
}
if ($proceed) {
    if (isset($_REQUEST['save_data'])) {


        redirect('admin/'.thisdoc());
    }
}

if ($proceed) {

    echo '<center>';

    show_message();
}

if ($proceed) {
    if(isset($_REQUEST['search'])) {

        $pform_fields=participantform__load();
        $fields=array();
        foreach ($pform_fields as $f) {
            $fields[]=$f['mysql_column_name'];
        }
        $field_names=array();
        foreach ($pform_fields as $f) {
            $field_names[$f['mysql_column_name']]=lang($f['name_lang']);
        }

        // sanitize search_for
        $columns=array();
        if (isset($_REQUEST['search_for']) && is_array($_REQUEST['search_for'])) {
            foreach ($_REQUEST['search_for'] as $k=>$v) if (in_array($k,$fields)) $columns[]=$k;
        }

        if (count($columns)==0) {
            message(lang('no_data_columns_selected'));
            redirect('admin/'.thisdoc());
        } else {
            $query="SELECT count(*) as num_matches, ".implode(', ',$columns)."
                    FROM ".table('participants')."
                    GROUP BY ".implode(', ',$columns)."
                    HAVING num_matches>1
                    ORDER BY num_matches DESC";
            $result=or_query($query); $dupvals=array();
            while ($line = pdo_fetch_assoc($result)) {
                $dupvals[]=$line;
            }
            if (check_allow('participants_edit')) {
                echo javascript__edit_popup();
            }
            $part_statuses=participant_status__get_statuses();
            $cols=participant__get_result_table_columns('result_table_search_duplicates');

            echo '<TABLE class="or_listtable"><thead>';
            echo '<TR style="background: '.$color['list_header_background'].'; color: '.$color['list_header_textcolor'].';">';
            echo '<TD>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</TD>';
            echo participant__get_result_table_headcells($cols,false);
            echo '</TR></thead>
                    <tbody>';
            $num_cols=count($cols)+1;
            foreach ($dupvals as $dv) {
                $mvals=array(); $pars=array(); $qclause=array();
                foreach ($columns as $c) {
                    $mvals[]=$field_names[$c].': '.$dv[$c];
                    $pars[':'.$c]=$dv[$c];
                    $qclause[]=' '.$c.' = :'.$c.' ';
                }
                echo '<TR><TD colspan="'.$num_cols.'"><B>'.implode(", ",$mvals).'</B></TD></TR>';
                $query="SELECT * FROM ".table('participants')."
                        WHERE ".implode(" AND ",$qclause)."
                        ORDER BY creation_time";
                $result=or_query($query,$pars); $shade=false;
                while ($p = pdo_fetch_assoc($result)) {
                    echo '<tr class="small"';
                    if ($shade) echo ' bgcolor="'.$color['list_shade1'].'"';
                    else echo 'bgcolor="'.$color['list_shade2'].'"';
                    echo '>';
                    echo '<TD bgcolor="'.$color['content_background_color'].'"></TD>';
                    echo participant__get_result_table_row($cols,$p);
                    echo '</tr>';
                    if ($shade) $shade=false; else $shade=true;
                }
            }
            echo '</tbody></TABLE>';
        }
    } else if(isset($_REQUEST['search_special'])){

        $pform_fields=participantform__load();
        $fields=array();
        foreach ($pform_fields as $f) {
            $fields[]=$f['mysql_column_name'];
        }
        $field_names=array();
        foreach ($pform_fields as $f) {
            $field_names[$f['mysql_column_name']]=lang($f['name_lang']);
        }

        //doing special search
        $query="-- script para encontrar registros repetidos en varias condiciones en ORSEE
        select distinct *
        from (
            -- busqueda por nombre repetido
            select participant_id, fname, lname, email, cedula_ciudadano, phone_number
            from (
            select p2.participant_id, p2.fname, p2.lname, p2.email, p2.cedula_ciudadano, p2.phone_number
            from (
                select p.participant_id, p.fname, p.lname, p.email, p.cedula_ciudadano, p.phone_number
                from (
                    select fname, lname, count(participant_id) conteo
                    from or_participants
                    where status_id in (select status_id from or_participant_statuses where status_id in (select content_name from or_lang where content_type='participant_status_name' and en='Active'))
                    group by fname, lname
                    having count(participant_id)>1
                ) a
                inner join or_participants p on p.fname=a.fname and p.lname=a.lname
            ) z
            inner join or_participants p2 on p2.participant_id<>z.participant_id and p2.fname=z.fname and p2.lname=z.lname
            where ((p2.email=z.email) or (p2.phone_number=z.phone_number) or (p2.cedula_ciudadano=z.cedula_ciudadano))
            and p2.cedula_ciudadano is not null and p2.cedula_ciudadano <> ''
            and z.cedula_ciudadano is not null and z.cedula_ciudadano<>''
            order by 2,3,4,5,6
            ) m

            union

            -- repetidos por cedula
            select participant_id, fname, lname, email, cedula_ciudadano, phone_number
            from (
            select p.participant_id, p.fname, p.lname, p.email, p.cedula_ciudadano, p.phone_number
            from (
                select cedula_ciudadano, count(participant_id) conteo
                from or_participants
                where cedula_ciudadano is not null and cedula_ciudadano<>''
                and status_id in (select status_id from or_participant_statuses where status_id in (select content_name from or_lang where content_type='participant_status_name' and en='Active'))
                group by cedula_ciudadano
                having count(participant_id)>1
            ) b
            inner join or_participants p on p.cedula_ciudadano=b.cedula_ciudadano
            order by 5
            ) n

            union

            -- busqueda de telefonos similares
            select participant_id, fname, lname, email, cedula_ciudadano, phone_number
            from (
            select p.participant_id, p.fname, p.lname, p.email, p.cedula_ciudadano, p.phone_number
            from (
                select phone_number, count(participant_id) conteo
                from or_participants
                where phone_number is not null and phone_number<>''
                and status_id in (select status_id from or_participant_statuses where status_id in (select content_name from or_lang where content_type='participant_status_name' and en='Active'))
                group by phone_number
                having count(participant_id)>1
            ) c
            inner join or_participants p on p.phone_number=c.phone_number
            order by 6
            ) oo
        ) as d";

        $columns=array();
        $columns[]='participant_id';
        $columns[]='fname';
        $columns[]='lname';
        $columns[]='email';
        $columns[]='cedula_ciudadano';
        $columns[]='phone_number';

        $result=or_query($query); $dupvals=array();
        while ($line = pdo_fetch_assoc($result)) {
            $dupvals[]=$line;
        }
        if (check_allow('participants_edit')) {
            echo javascript__edit_popup();
        }
        $part_statuses=participant_status__get_statuses();
        $cols=participant__get_result_table_columns('result_table_search_duplicates');

        echo '<TABLE class="or_listtable"><thead>';
        echo '<TR style="background: '.$color['list_header_background'].'; color: '.$color['list_header_textcolor'].';">';
        echo '<TD>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</TD>';
        echo participant__get_result_table_headcells($cols,false);
        echo '</TR></thead>
                <tbody>';
        $num_cols=count($cols)+1;
        foreach ($dupvals as $dv) {
            $mvals=array(); $pars=array(); $qclause=array();
            foreach ($columns as $c) {
                $mvals[]=$field_names[$c].': '.$dv[$c];
                $pars[':'.$c]=$dv[$c];
                $qclause[]=' '.$c.' = :'.$c.' ';
            }
            echo '<TR><TD colspan="'.$num_cols.'"><B>'.implode(", ",$mvals).'</B></TD></TR>';
            $query="SELECT * FROM ".table('participants')."
                    WHERE ".implode(" AND ",$qclause)."
                    ORDER BY creation_time";
            $result=or_query($query,$pars); $shade=false;
            while ($p = pdo_fetch_assoc($result)) {
                echo '<tr class="small"';
                if ($shade) echo ' bgcolor="'.$color['list_shade1'].'"';
                else echo 'bgcolor="'.$color['list_shade2'].'"';
                echo '>';
                echo '<TD bgcolor="'.$color['content_background_color'].'"></TD>';
                echo participant__get_result_table_row($cols,$p);
                echo '</tr>';
                if ($shade) $shade=false; else $shade=true;
            }
        }
        echo '</tbody></TABLE>';

    }
     else {

        $pform_fields=participantform__load();
        $field_names=array();
        foreach ($pform_fields as $f) {
            $field_names[$f['mysql_column_name']]=lang($f['name_lang']);
        }

        echo '<FORM action="participants_duplicates.php" method="POST">';
        echo '<B></B>';

        echo '<TABLE class="or_formtable"><TR><TD>
                <TABLE width="100%" border=0 class="or_panel_title"><TR>
                        <TD style="background: '.$color['panel_title_background'].'; color: '.$color['panel_title_textcolor'].'" align="center">
                            '.lang('search_duplicates_on_the_following_combined_characteristics').'
                        </TD>
                </TR></TABLE>
                </TD></TR>
                <TR><TD>';
        $num_cols=4; $c=0;
        echo '<TABLE><TR>';
        foreach ($field_names as $m=>$n) {
            $c++;
            if ($c>$num_cols) {
                echo '</TR><TR>';
                $c=1;
            }
            echo '<TD><INPUT type="checkbox" name="search_for['.$m.']" value="y">'.$n.'</TD>';
        }
        if ($c<$num_cols) for($i=$c; $i<$num_cols; $i++) echo '<TD></TD>';
        echo '</TR><TR><TD align="center" colspan="'.$num_cols.'">
                <INPUT class="button" type="submit" name="search" value="'.lang('search').'">
                <INPUT class="button" type="submit" name="search_special" value="'.lang('search_special').'">
                </TD></TR>';
        echo '</TABLE>';
        echo '</TD></TR></TABLE>';
        echo '</FORM>';
    }
}

if ($proceed) {
    echo '</center>';
}
include ("footer.php");
?>
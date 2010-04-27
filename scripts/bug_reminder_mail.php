<?php
# This page sends an E-mail if a due date is getting near
# includes all due_dates not met
require_once( '../../../core.php' );
$t_login	= config_get( 'plugin_Reminder_reminder_login' );
$ok=auth_attempt_script_login( $t_login ); 
$t_core_path = config_get( 'core_path' );
require_once( $t_core_path.'bug_api.php' );
require_once( $t_core_path.'email_api.php' );
require_once( $t_core_path.'bugnote_api.php' );

$t_bug_table	= db_get_table( 'mantis_bug_table' );
$t_man_table	= db_get_table( 'mantis_project_user_list_table' );

$t_rem_project	= config_get( 'plugin_Reminder_reminder_project_id' );
$t_rem_days		= config_get( 'plugin_Reminder_reminder_days_treshold' );
$t_rem_status	= config_get( 'plugin_Reminder_reminder_bug_status' );
$t_rem_body		= config_get( 'plugin_Reminder_reminder_mail_subject' );
$t_rem_store	= config_get( 'plugin_Reminder_reminder_store_as_note' );
$t_rem_ignore	= config_get( 'plugin_Reminder_reminder_ignore_unset' );
$t_rem_ign_past	= config_get( 'plugin_Reminder_reminder_ignore_past' );
$t_rem_handler 	= config_get( 'plugin_Reminder_reminder_handler' );
$t_rem_group1	= config_get( 'plugin_Reminder_reminder_group_issues' );
$t_rem_group2	= config_get( 'plugin_Reminder_reminder_group_project' );
$t_rem_manager	= config_get( 'plugin_Reminder_reminder_manager_overview' );
$t_rem_subject	= config_get( 'plugin_Reminder_reminder_group_subject' );
$t_rem_body1	= config_get( 'plugin_Reminder_reminder_group_body1' );
$t_rem_body2	= config_get( 'plugin_Reminder_reminder_group_body2' );

//
// access level for manager= 70
// this needs to be made flexible
// we will only produce overview for those projects that have a separate manager
//
$baseline	= time(true)+ ($t_rem_days*24*60*60);
$basenow	= time(true);
if ( ON == $t_rem_handler ) {
	$query = "select id,handler_id,project_id from $t_bug_table where status=$t_rem_status and due_date<=$baseline and handler_id<>0 ";
	if ( ON == $t_rem_ignore ) {
		$query .=" and due_date>1" ;
	} else{
		if ( ON == $t_rem_ign_past ) {
			$query .=" and due_date>=$basenow" ;
		}
	}
	if ( $t_rem_project>0 ) {
		$query .=" and project_id=t_rem_project" ;
	}
	if ( ON == $t_rem_group1 ) {
		$query .=" order by handler_id" ;
	}
	if ( ON == $t_rem_group2 ) {
		$query .=" order by project_id,handler_id" ;
	}
	$results = mysql_query( $query );
	$resnum=mysql_num_rows($results);
	if ( OFF == $t_rem_group1 ) {
		if ($results) {
			while ($row1 = mysql_fetch_array($results, MYSQL_NUM)) {
				$id 		= $row1[0];
				$handler	= $row1[1];
				$list = string_get_bug_view_url_with_fqdn( $id, $handler2 );
				$body  = $t_rem_body1. " \n\n";
				$body .= $list. " \n\n";
				$body .= $t_rem_body2;
				$result = email_group_reminder( $handler, $body );
				# Add reminder as bugnote if store reminders option is ON.
				if ( ON == $t_rem_store ) {
					$t_attr = '|' . implode( '|', $handler ) . '|';
					bugnote_add( $id, $t_rem_body, 0, config_get( 'default_reminder_view_status' ) == VS_PRIVATE, REMINDER, $t_attr, NULL, FALSE );
				}
			}
		} 
	} else {
		if ($results){
			$start = true ;
			$list= "";
			// first group and store reminder per issue
			while ($row1 = mysql_fetch_array($results, MYSQL_NUM)) {
				$id 		= $row1[0];
				$handler	= $row1[1];
				$project	= $row1[2];
				if ($start){
					$handler2 = $handler ;
					$start = false ;
				}
				if ($handler==$handler2){
					$list .=" \n\n"; 
					$list .= string_get_bug_view_url_with_fqdn( $id, $handler2 );
					# Add reminder as bugnote if store reminders option is ON.
					if ( ON == $t_rem_store ) {
						$t_attr = '|' . implode( '|', $handler2 ) . '|';
						bugnote_add( $id, $t_rem_body, 0, config_get( 'default_reminder_view_status' ) == VS_PRIVATE, REMINDER, $t_attr, NULL, FALSE );
					}
				} else {
					// now send the grouped email
					$body  = $t_rem_body1. " \n\n";
					$body .= $list. " \n\n";
					$body .= $t_rem_body2;
					$result = email_group_reminder( $handler2, $body);
					$handler2 = $handler ;
					$list =" \n\n"; 
					$list= string_get_bug_view_url_with_fqdn( $id, $handler2 );
					# Add reminder as bugnote if store reminders option is ON.
					if ( ON == $t_rem_store ) {
						$t_attr = '|' . implode( '|', $handler2 ) . '|';
						bugnote_add( $id, $t_rem_body, 0, config_get( 'default_reminder_view_status' ) == VS_PRIVATE, REMINDER, $t_attr, NULL, FALSE );
					}
				}
			}
			// handle last one
			if ($resnum>0){
				// now send the grouped email
				$body  = $t_rem_body1. " \n\n";
				$body .= $list. " \n\n";
				$body .= $t_rem_body2;
				$result = email_group_reminder( $handler2, $body);
			
			} 
			//
		}
	}
}

if ( ON == $t_rem_manager ) {
	// select relevant issues in combination with an assigned manager to the project
	$query  = "select id,handler_id,user_id from $t_bug_table,$t_man_table where status=$t_rem_status and due_date<=$baseline ";
	if ( ON == $t_rem_ignore ) {
		$query .=" and due_date>1" ;
	} else{
		if ( ON == $t_rem_ign_past ) {
			$query .=" and due_date>=$basenow" ;
		}
	}
	if ( $t_rem_project>0 ) {
		$query .=" and project_id=t_rem_project" ;
	}
	$query .=" and $t_bug_table.project_id=$t_man_table.project_id and $t_man_table.access_level=70" ;
	$query .=" order by $t_man_table.project_id,$t_man_table.user_id" ;
	$results = mysql_query( $query );
	$resnum=mysql_num_rows($results);
	if ($results){
		$start = true ;
		$list= "";
		// first group and store reminder per issue
		while ($row1 = mysql_fetch_array($results, MYSQL_NUM)) {
			$id 		= $row1[0];
			$handler	= $row1[1];
			$manager	= $row1[2];
			if ($start){
				$man2 = $manager ;
				$start = false ;
			}
			if ($manager==$man2){
				$list .=" \n\n"; 
				$list .= string_get_bug_view_url_with_fqdn( $id, $man2 );
			} else {
				// now send the grouped email
				$body  = $t_rem_body1. " \n\n";
				$body .= $list. " \n\n";
				$body .= $t_rem_body2;
				$result = email_group_reminder( $man2, $body);
				$man2 = $manager ;
				$list= string_get_bug_view_url_with_fqdn( $id, $man2 );
				$list .= "<br>";					
			}
		}
		// handle last one
		if ($resnum>0){
			// now send the grouped email
			$body  = $t_rem_body1. " \n\n";
			$body .= $list. " \n\n";
			$body .= $t_rem_body2;
			$result = email_group_reminder( $man2, $body);
		
		}		//
	} 
}

# Send Grouped reminder
function email_group_reminder( $p_user_id, $issues ) {
	$t_username = user_get_field( $p_user_id, 'username' );
	$t_email = user_get_email( $p_user_id );
	$t_subject = config_get( 'plugin_Reminder_reminder_group_subject' );
	$t_message = $issues ;
	if( !is_blank( $t_email ) ) {
		email_store( $t_email, $t_subject, $t_message );
		if( OFF == config_get( 'email_send_using_cronjob' ) ) {
			email_send_all();
		}
	}
}
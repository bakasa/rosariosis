<?php

require_once 'ProgramFunctions/MarkDownHTML.fnc.php';

DrawHeader( _( 'Gradebook' ) . ' - ' . ProgramTitle() );

if ( ! UserCoursePeriod() )
{
	echo ErrorMessage( array( _( 'No courses assigned to teacher.' ) ), 'fatal' );
}
	
$course_id = DBGet(DBQuery("SELECT COURSE_ID FROM COURSE_PERIODS WHERE COURSE_PERIOD_ID='".UserCoursePeriod()."'"));
$course_id = $course_id[1]['COURSE_ID'];

$_ROSARIO['allow_edit'] = true;
//unset($_SESSION['_REQUEST_vars']['assignment_type_id']);
//unset($_SESSION['_REQUEST_vars']['assignment_id']);

if ( isset( $_POST['day_tables'], $_POST['month_tables'], $_POST['year_tables'] ) )
{
	$requested_dates = RequestedDates(
		$_REQUEST['day_tables'],
		$_REQUEST['month_tables'],
		$_REQUEST['year_tables']
	);

	$_REQUEST['tables'] = array_replace_recursive( $_REQUEST['tables'], $requested_dates );

	$_POST['tables'] = array_replace_recursive( $_POST['tables'], $requested_dates );
}

if ( isset( $_POST['tables'] )
	&& count( $_POST['tables'] ) )
{
	$table = $_REQUEST['table'];

	foreach ( (array) $_REQUEST['tables'] as $id => $columns)
	{
		// FJ textarea fields MarkDown sanitize.
		if ( isset( $columns['DESCRIPTION'] ) )
		{
			$columns['DESCRIPTION'] = SanitizeMarkDown( $_POST['tables'][ $id ]['DESCRIPTION'] );
		}

		// FJ added SQL constraint TITLE & POINTS are not null.
		if ( ( ! isset( $columns['TITLE'] )
				|| ! empty( $columns['TITLE'] ) )
			&& ( ! isset( $columns['POINTS'] )
				|| $columns['POINTS'] !== '' ) )
		{
			// FJ fix SQL bug invalid numeric data.
			// FJ default points.
			if ( ( ! isset( $columns['POINTS'] )
					|| ( is_numeric( $columns['POINTS'] )
						&& intval( $columns['POINTS'] ) >= 0 ) )
				&& ( ! isset( $columns['DEFAULT_POINTS'] )
					|| $columns['DEFAULT_POINTS'] === ''
					|| $columns['DEFAULT_POINTS'] === '*'
					|| ( is_numeric( $columns['DEFAULT_POINTS'] )
						&& intval( $columns['DEFAULT_POINTS'] ) >= 0 ) ) )
			{
				// FJ fix SQL bug invalid sort order.
				if ( empty( $columns['SORT_ORDER'] )
					|| is_numeric( $columns['SORT_ORDER'] ) )
				{
					if ( $id != 'new')
					{
						if ( $columns['ASSIGNMENT_TYPE_ID']
							&& $columns['ASSIGNMENT_TYPE_ID'] != $_REQUEST['assignment_type_id'] )
						{
							$_REQUEST['assignment_type_id'] = $columns['ASSIGNMENT_TYPE_ID'];
						}

						$sql = "UPDATE $table SET ";

						//if ( ! $columns['COURSE_ID'] && $table=='GRADEBOOK_ASSIGNMENTS')
						//	$columns['COURSE_ID'] = 'N';

						foreach ( (array) $columns as $column => $value )
						{
							if ( ( $column === 'DUE_DATE'
								|| $column === 'ASSIGNED_DATE' )
								&& $value !== '' )
							{
								if ( ! VerifyDate( $value ) )
								{
									$error[] = _( 'Some dates were not entered correctly.' );
								}
							}
							elseif ( $column == 'COURSE_ID'
								&& $value == 'Y'
								&& $table == 'GRADEBOOK_ASSIGNMENTS')
							{
								$value = $course_id;
								$sql .= 'COURSE_PERIOD_ID=NULL,';
							}
							elseif ( $column == 'COURSE_ID'
								&& $table == 'GRADEBOOK_ASSIGNMENTS')
							{
								$column = 'COURSE_PERIOD_ID';
								$value = UserCoursePeriod();
								$sql .= 'COURSE_ID=NULL,';
							}
							elseif ( $column == 'FINAL_GRADE_PERCENT'
								&& $table == 'GRADEBOOK_ASSIGNMENT_TYPES')
							{
								$value = preg_replace( '/[^0-9.]/', '', $value ) / 100;
							}
							// FJ default points.
							elseif ( $column == 'DEFAULT_POINTS'
								&& $value == '*'
								&& $table == 'GRADEBOOK_ASSIGNMENTS' )
							{
								$value = '-1';
							}

							$sql .= $column . "='". $value . "',";
						}

						$sql = mb_substr( $sql, 0, -1 ) . " WHERE " . mb_substr( $table, 10, -1 ) . "_ID='" . $id . "'";

						$go = true;

						$gradebook_assignment_update = true;
					}
					else
					{
						$sql = "INSERT INTO $table ";

						if ( $table == 'GRADEBOOK_ASSIGNMENTS')
						{
							if ( $columns['ASSIGNMENT_TYPE_ID'] )
							{
								$_REQUEST['assignment_type_id'] = $columns['ASSIGNMENT_TYPE_ID'];

								unset( $columns['ASSIGNMENT_TYPE_ID'] );
							}

							$id = DBGet( DBQuery( "SELECT " . db_seq_nextval( 'GRADEBOOK_ASSIGNMENTS_SEQ' ) . ' AS ID' ) );

							$id = $id[1]['ID'];

							$fields = "ASSIGNMENT_ID,ASSIGNMENT_TYPE_ID,STAFF_ID,MARKING_PERIOD_ID,";

							$values = $id . ",'" . $_REQUEST['assignment_type_id'] . "','" .
								User( 'STAFF_ID' ) . "','" . UserMP() . "',";

							$_REQUEST['assignment_id'] = $id;
						}
						elseif ( $table == 'GRADEBOOK_ASSIGNMENT_TYPES')
						{
							$id = DBGet( DBQuery( "SELECT " . db_seq_nextval( 'GRADEBOOK_ASSIGNMENT_TYPES_SEQ' ) . ' AS ID' ) );

							$id = $id[1]['ID'];

							$fields = "ASSIGNMENT_TYPE_ID,STAFF_ID,COURSE_ID,";

							$values = $id . ",'" . User( 'STAFF_ID' ) . "','" . $course_id . "',";

							$_REQUEST['assignment_type_id'] = $id;
						}

						$go = false;

						if ( ! $columns['COURSE_ID']
							&& $_REQUEST['table'] == 'GRADEBOOK_ASSIGNMENTS' )
						{
							$columns['COURSE_ID'] = 'N';
						}

						foreach ( (array) $columns as $column => $value )
						{
							if ( ( $column === 'DUE_DATE'
								|| $column === 'ASSIGNED_DATE' )
								&& $value !== '' )
							{
								if ( ! VerifyDate( $value ) )
								{
									$error[] = _( 'Some dates were not entered correctly.' );
								}
							}
							elseif ( $column == 'COURSE_ID'
								&& $value == 'Y' )
							{
								$value = $course_id;
							}
							elseif ( $column == 'COURSE_ID')
							{
								$column = 'COURSE_PERIOD_ID';

								$value = UserCoursePeriod();
							}
							elseif ( $column == 'FINAL_GRADE_PERCENT'
								&& $table == 'GRADEBOOK_ASSIGNMENT_TYPES' )
							{
								$value = preg_replace('/[^0-9.]/','',$value) / 100;
							}
							//FJ default points
							elseif ( $column == 'DEFAULT_POINTS'
								&& $value == '*'
								&& $table == 'GRADEBOOK_ASSIGNMENTS' )
							{
								$value = '-1';
							}

							if ( $value != '' )
							{
								$fields .= $column . ',';

								$values .= "'" . $value . "',";

								$go = true;
							}
						}
						$sql .= '(' . mb_substr( $fields, 0, -1 ) . ') values(' . mb_substr( $values, 0, -1 ) . ')';
					}
				}
				else
					$error[] = _( 'Please enter a valid Sort Order.' );
			}
			else
				$error[] = _( 'Please enter valid Numeric data.' );
		}
		else
			$error[] = _( 'Please fill in the required fields' );

		if ( !isset($error) && $go)
		{
			DBQuery($sql);
			
			if ( $table=='GRADEBOOK_ASSIGNMENTS')
			{
				if ( $gradebook_assignment_update)
				{
					//hook
					do_action('Grades/Assignments.php|update_assignment');
				}
				else
					//hook
					do_action('Grades/Assignments.php|create_assignment');
			}
		}
	}
	unset($_REQUEST['tables']);
	unset($_SESSION['_REQUEST_vars']['tables']);
}

// DELETE
if ( isset( $_REQUEST['modfunc'] )
	&& $_REQUEST['modfunc'] === 'delete' )
{
	// Assignment
	if ( $_REQUEST['assignment_id'] )
	{
		$prompt_title = _( 'Assignment' );

		$sql = "DELETE
			FROM GRADEBOOK_ASSIGNMENTS
			WHERE ASSIGNMENT_ID='" . $_REQUEST['assignment_id'] . "'";
	}
	// Assignment Type
	else
	{
		$cp_qtr_mps_titles = '';

		// Get MPs list associated to Course Period
		$cp_mp_id = DBGet( DBQuery( "SELECT MARKING_PERIOD_ID
			FROM COURSE_PERIODS
			WHERE COURSE_PERIOD_ID='" . UserCoursePeriod() . "'" ) );

		$cp_mp_id = $cp_mp_id[1]['MARKING_PERIOD_ID'];

		$cp_mp = GetMP( $cp_mp_id, 'MP' );

		if ( $cp_mp !== 'QTR'
			&& $cp_mp !== 'PRO' )
		{
			$cp_qtr_mps_list = GetChildrenMP( $cp_mp, $cp_mp_id );

			$cp_qtr_mps_array = explode( ",", $cp_qtr_mps_list );

			foreach ( (array) $cp_qtr_mps_array as $cp_qtr_mp )
			{
				if ( GetMP( trim( $cp_qtr_mp, "'" ), 'MP' ) === 'QTR' )
				{
					$cp_qtr_mps_titles .= GetMP( trim( $cp_qtr_mp, "'" ), 'TITLE' ) . ', ';
				}
			}

			$cp_qtr_mps_titles = ' (' . mb_substr( $cp_qtr_mps_titles, 0, -2 ) . ')';
		}

		// FJ More explicit Assignment Type deletion Prompt message
		$prompt_title = _( 'Assignment Type as well as its Assignments & associated Grades' ) .
			$cp_qtr_mps_titles;

		$sql = "DELETE
			FROM GRADEBOOK_ASSIGNMENT_TYPES
			WHERE ASSIGNMENT_TYPE_ID='" . $_REQUEST['assignment_type_id'] . "'";
	}

	// Confirm Delete
	if ( DeletePrompt( $prompt_title ) )
	{
		DBQuery($sql);
		if ( ! $_REQUEST['assignment_id'])
		{
			$assignments_RET = DBGet(DBQuery("SELECT ASSIGNMENT_ID FROM GRADEBOOK_ASSIGNMENTS WHERE ASSIGNMENT_TYPE_ID='".$_REQUEST['assignment_type_id']."'"));
			if (count($assignments_RET))
			{
				foreach ( (array) $assignments_RET as $assignment_id)
				{
					DBQuery("DELETE FROM GRADEBOOK_GRADES WHERE ASSIGNMENT_ID='".$assignment_id['ASSIGNMENT_ID']."'");

					$_REQUEST['assignment_id'] = $assignment_id['ASSIGNMENT_ID'];

					//hook
					do_action('Grades/Assignments.php|delete_assignment');

					unset($_REQUEST['assignment_id']);
				}
			}
			DBQuery("DELETE FROM GRADEBOOK_ASSIGNMENTS WHERE ASSIGNMENT_TYPE_ID='".$_REQUEST['assignment_type_id']."'");
			unset($_REQUEST['assignment_type_id']);
		}
		else
		{
			DBQuery("DELETE FROM GRADEBOOK_GRADES WHERE ASSIGNMENT_ID='".$_REQUEST['assignment_id']."'");

			//hook
			do_action('Grades/Assignments.php|delete_assignment');

			unset($_REQUEST['assignment_id']);
		}
		unset($_REQUEST['modfunc']);
	}
}

echo ErrorMessage( $error );

if (empty($_REQUEST['modfunc']))
{
	// ASSIGNMENT TYPES
	$sql = "SELECT ASSIGNMENT_TYPE_ID,TITLE,SORT_ORDER FROM GRADEBOOK_ASSIGNMENT_TYPES WHERE STAFF_ID='".User('STAFF_ID')."' AND COURSE_ID=(SELECT COURSE_ID FROM COURSE_PERIODS WHERE COURSE_PERIOD_ID='".UserCoursePeriod()."') ORDER BY SORT_ORDER,TITLE";
	$QI = DBQuery($sql);
	$types_RET = DBGet($QI);

	if ( $_REQUEST['assignment_id']!='new' && $_REQUEST['assignment_type_id']!='new')
	{
		$delete_url = "'Modules.php?modname=" . $_REQUEST['modname'] .
			'&modfunc=delete&assignment_type_id=' . $_REQUEST['assignment_type_id'] .
			'&assignment_id=' . $_REQUEST['assignment_id'] . "'";

		$delete_button = '<input type="button" value="' . _( 'Delete' ) . '" onClick="javascript:ajaxLink(' . $delete_url . ');" />';
	}

	// ADDING & EDITING FORM
	if ( $_REQUEST['assignment_id'] && $_REQUEST['assignment_id']!='new')
	{
		$sql = "SELECT ASSIGNMENT_TYPE_ID,TITLE,ASSIGNED_DATE,DUE_DATE,POINTS,COURSE_ID,DESCRIPTION,DEFAULT_POINTS,
				CASE WHEN DUE_DATE<ASSIGNED_DATE THEN 'Y' ELSE NULL END AS DATE_ERROR,
				CASE WHEN ASSIGNED_DATE>(SELECT END_DATE FROM SCHOOL_MARKING_PERIODS WHERE MARKING_PERIOD_ID='".UserMP()."') THEN 'Y' ELSE NULL END AS ASSIGNED_ERROR,
				CASE WHEN DUE_DATE>(SELECT END_DATE+1 FROM SCHOOL_MARKING_PERIODS WHERE MARKING_PERIOD_ID='".UserMP()."') THEN 'Y' ELSE NULL END AS DUE_ERROR
				FROM GRADEBOOK_ASSIGNMENTS
				WHERE ASSIGNMENT_ID='".$_REQUEST['assignment_id']."'";
		$QI = DBQuery($sql);
		$RET = DBGet($QI);
		$RET = $RET[1];
		$title = $RET['TITLE'];
	}
	elseif ( $_REQUEST['assignment_type_id'] && $_REQUEST['assignment_type_id']!='new' && $_REQUEST['assignment_id']!='new')
	{
		$sql = "SELECT at.TITLE,at.FINAL_GRADE_PERCENT,SORT_ORDER,COLOR,
				(SELECT sum(FINAL_GRADE_PERCENT) FROM GRADEBOOK_ASSIGNMENT_TYPES WHERE COURSE_ID=(SELECT COURSE_ID FROM COURSE_PERIODS WHERE COURSE_PERIOD_ID='".UserCoursePeriod()."') AND STAFF_ID='".User('STAFF_ID')."') AS TOTAL_PERCENT
				FROM GRADEBOOK_ASSIGNMENT_TYPES at
				WHERE at.ASSIGNMENT_TYPE_ID='".$_REQUEST['assignment_type_id']."'";
		$QI = DBQuery($sql);
		$RET = DBGet($QI,array('FINAL_GRADE_PERCENT' => '_makePercent'));
		$RET = $RET[1];
		$title = $RET['TITLE'];
	}
	elseif ( $_REQUEST['assignment_id']=='new')
	{
		$title = _('New Assignment');
		$new = true;
	}
	elseif ( $_REQUEST['assignment_type_id']=='new')
	{
		$sql = "SELECT sum(FINAL_GRADE_PERCENT) AS TOTAL_PERCENT FROM GRADEBOOK_ASSIGNMENT_TYPES WHERE COURSE_ID=(SELECT COURSE_ID FROM COURSE_PERIODS WHERE COURSE_PERIOD_ID='".UserCoursePeriod()."') AND STAFF_ID='".User('STAFF_ID')."'";
		$QI = DBQuery($sql);
		$RET = DBGet($QI,array('FINAL_GRADE_PERCENT' => '_makePercent'));
		$RET = $RET[1];
		$title = _('New Assignment Type');
	}

	if ( $_REQUEST['assignment_id'])
	{
		echo '<form action="Modules.php?modname='.$_REQUEST['modname'].'&assignment_type_id='.$_REQUEST['assignment_type_id'];
		if ( $_REQUEST['assignment_id']!='new')
			echo '&assignment_id='.$_REQUEST['assignment_id'];
		echo '&table=GRADEBOOK_ASSIGNMENTS" method="POST">';

		DrawHeader($title,$delete_button.SubmitButton(_('Save')));
		$header .= '<table class="width-100p valign-top fixed-col">';
		$header .= '<tr class="st">';

		//FJ title & points are required
		$header .= '<td>' . TextInput(
			$RET['TITLE'],
			'tables[' . $_REQUEST['assignment_id'] . '][TITLE]',
			_( 'Title' ),
			'required'
		) . '</td>';

		$header .= '<td>' . TextInput(
			$RET['POINTS'],
			'tables[' . $_REQUEST['assignment_id'] . '][POINTS]',
			_( 'Points' ),
			'required size=4 maxlength=4 min=0'
		) . '</td>';

		//FJ default points
		if ( $RET['DEFAULT_POINTS']=='-1')
			$RET['DEFAULT_POINTS'] = '*';

		$header .= '<td>' . TextInput(
			$RET['DEFAULT_POINTS'],
			'tables[' . $_REQUEST['assignment_id'] . '][DEFAULT_POINTS]',
			_( 'Default Points' ) .
				'<div class="tooltip"><i>' .
					_( 'Enter an asterisk (*) to excuse student' ) .
				'</i></div>',
			' size=4 maxlength=4'
		) . '</td>';

		$header .= '</tr><tr class="st">';

		$header .= '<td colspan="2">' . TextAreaInput($RET['DESCRIPTION'],'tables['.$_REQUEST['assignment_id'].'][DESCRIPTION]',_('Description')) . '</td>';
		$header .= '<td>' . CheckboxInput($RET['COURSE_ID'],'tables['.$_REQUEST['assignment_id'].'][COURSE_ID]',_('Apply to all Periods for this Course'),'',$_REQUEST['assignment_id']=='new') . '</td>';

		$header .= '</tr><tr class="st">';

		foreach ( (array) $types_RET as $type)
			$assignment_type_options[$type['ASSIGNMENT_TYPE_ID']] = $type['TITLE'];

		$header .= '<td>' . SelectInput($RET['ASSIGNMENT_TYPE_ID']?$RET['ASSIGNMENT_TYPE_ID']:$_REQUEST['assignment_type_id'],'tables['.$_REQUEST['assignment_id'].'][ASSIGNMENT_TYPE_ID]',_('Assignment Type'),$assignment_type_options,false) . '</td>';
		$header .= '<td>' . DateInput($new && Preferences('DEFAULT_ASSIGNED','Gradebook')=='Y'?DBDate():$RET['ASSIGNED_DATE'],'tables['.$_REQUEST['assignment_id'].'][ASSIGNED_DATE]',_('Assigned'),! $new) . '</td>';
		$header .= '<td>' . DateInput($new && Preferences('DEFAULT_DUE','Gradebook')=='Y'?DBDate():$RET['DUE_DATE'],'tables['.$_REQUEST['assignment_id'].'][DUE_DATE]',_('Due'),! $new) . '</td>';
		$header .= '</tr>';

		if ( $RET['DATE_ERROR'] == 'Y' )
		{
			$error[] = _( 'Due date is before assigned date!' );
		}

		if ( $RET['ASSIGNED_ERROR'] == 'Y' )
		{
			$error[] = _( 'Assigned date is after end of quarter!' );
		}

		if ( $RET['DUE_ERROR'] == 'Y' )
		{
			$error[] = _( 'Due date is after end of quarter!' );
		}

		$header .= '<tr><td class="valign-top" colspan="3">' . ErrorMessage( $error ) . '</td></tr>';
		$header .= '</table>';
	}
	elseif ( $_REQUEST['assignment_type_id'])
	{
		echo '<form action="Modules.php?modname='.$_REQUEST['modname'].'&table=GRADEBOOK_ASSIGNMENT_TYPES';
		if ( $_REQUEST['assignment_type_id']!='new')
			echo '&assignment_type_id='.$_REQUEST['assignment_type_id'];
		echo '" method="POST">';
		DrawHeader($title,$delete_button.SubmitButton(_('Save')));
		$header .= '<table class="width-100p valign-top fixed-col">';
		$header .= '<tr class="st">';

		//FJ title is required
		$header .= '<td>' . TextInput(
			$RET['TITLE'],
			'tables[' . $_REQUEST['assignment_type_id'] . '][TITLE]',
			_( 'Title' ),
			'required'
		) . '</td>';

		if (Preferences('WEIGHT','Gradebook')=='Y')
		{
			$header .= '<td>' . TextInput(
				$RET['FINAL_GRADE_PERCENT'],
				'tables[' . $_REQUEST['assignment_type_id'] . '][FINAL_GRADE_PERCENT]',
				( $RET['FINAL_GRADE_PERCENT'] != 0 ? '' : '<span class="legend-red">' ) .
				_( 'Percent of Final Grade' ) .
				( $RET['FINAL_GRADE_PERCENT'] != 0 ? '' : '</span>' ) ) . '</td>';

			$header .= '<td>' . NoInput($RET['TOTAL_PERCENT']==1?'100%':'<span style="color:red">'.(100*$RET['TOTAL_PERCENT']).'%</span>',_('Percent Total')) . '</td>';
		}

		$header .= '<td>' . TextInput(
			$RET['SORT_ORDER'],
			'tables[' . $_REQUEST['assignment_type_id'] . '][SORT_ORDER]',
			_( 'Sort Order' ) ) . '</td>';

		/*$colors = array('#330099','#3366FF','#003333','#FF3300','#660000','#666666','#333366','#336633','purple','teal','firebrick','tan');
		foreach ( (array) $colors as $color)
		{
			$color_select[ $color ] = array('<span style="background-color:'.$color.';">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>','<span style="background-color:'.$color.';">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>');
		}

		$header .= '<td>' .  RadioInput($RET['COLOR'],'tables['.$_REQUEST['assignment_type_id'].'][COLOR]',_('Color'),$color_select) . '</td>';*/

		$header .= '<td>' . ColorInput(
			$RET['COLOR'],
			'tables[' . $_REQUEST['assignment_type_id'] . '][COLOR]',
			_( 'Color' ),
			'hidden'
		) . '</td>';

		$header .= '</tr></table>';
	}
	else
		$header = false;

	if ( $header)
	{
		DrawHeader($header);
		echo '</form>';
	}

	// DISPLAY THE MENU
	$LO_options = array('save'=>false,'search'=>false,'add'=>true,'responsive'=>false);

	if (count($types_RET))
	{
		if ( $_REQUEST['assignment_type_id'])
		{
			foreach ( (array) $types_RET as $key => $value)
			{
				if ( $value['ASSIGNMENT_TYPE_ID']==$_REQUEST['assignment_type_id'])
					$types_RET[ $key ]['row_color'] = Preferences('HIGHLIGHT');
			}
		}
	}

	echo '<div class="st">';
	$columns = array('TITLE' => _('Assignment Type'),'SORT_ORDER' => _('Order'));
	$link = array();
	$link['TITLE']['link'] = 'Modules.php?modname='.$_REQUEST['modname'].'&modfunc='.$_REQUEST['modfunc'];
	$link['TITLE']['variables'] = array('assignment_type_id' => 'ASSIGNMENT_TYPE_ID');
	$link['add']['link'] = 'Modules.php?modname='.$_REQUEST['modname'].'&assignment_type_id=new';
	$link['add']['first'] = 5; // number before add link moves to top

	ListOutput($types_RET,$columns,'Assignment Type','Assignment Types',$link,array(),$LO_options);
	echo '</div>';


	// ASSIGNMENTS
	if ( $_REQUEST['assignment_type_id'] && $_REQUEST['assignment_type_id']!='new' && count($types_RET))
	{
		$sql = "SELECT ASSIGNMENT_ID,TITLE,POINTS 
		FROM GRADEBOOK_ASSIGNMENTS 
		WHERE STAFF_ID='".User('STAFF_ID')."' 
		AND (COURSE_ID=(SELECT COURSE_ID FROM COURSE_PERIODS WHERE COURSE_PERIOD_ID='".UserCoursePeriod()."') OR COURSE_PERIOD_ID='".UserCoursePeriod()."') 
		AND ASSIGNMENT_TYPE_ID='".$_REQUEST['assignment_type_id']."' 
		AND MARKING_PERIOD_ID='".UserMP()."' 
		ORDER BY ".Preferences('ASSIGNMENT_SORTING','Gradebook')." DESC";
		$QI = DBQuery($sql);
		$assn_RET = DBGet($QI);

		if (count($assn_RET))
		{
			if ( $_REQUEST['assignment_id'] && $_REQUEST['assignment_id']!='new')
			{
				foreach ( (array) $assn_RET as $key => $value)
				{
					if ( $value['ASSIGNMENT_ID']==$_REQUEST['assignment_id'])
						$assn_RET[ $key ]['row_color'] = Preferences('HIGHLIGHT');
				}
			}
		}

		echo '<div class="st">';
		$columns = array('TITLE' => _('Assignment'),'POINTS' => _('Points'));
		$link = array();
		$link['TITLE']['link'] = 'Modules.php?modname='.$_REQUEST['modname'].'&assignment_type_id='.$_REQUEST['assignment_type_id'];
		$link['TITLE']['variables'] = array('assignment_id' => 'ASSIGNMENT_ID');
		$link['add']['link'] = 'Modules.php?modname='.$_REQUEST['modname'].'&assignment_type_id='.$_REQUEST['assignment_type_id'].'&assignment_id=new';
		$link['add']['first'] = 5; // number before add link moves to top

		ListOutput($assn_RET,$columns,'Assignment','Assignments',$link,array(),$LO_options);

		echo '</div>';
	}
}

function _makePercent($value,$column)
{
	return number_format($value*100,2).'%';
}

<?php
/*=======================================================\
|                        FrontHrm                        |
|--------------------------------------------------------|
|   Creator: Phương <trananhphuong83@gmail.com>          |
|   Date :   09-Jul-2017                                 |
|   Description: NotrinosERP Payroll & Hrm Module        |
|   Free software under GNU GPL                          |
|                                                        |
\=======================================================*/

$page_security = 'SA_EMPL';
$path_to_root  = '../../..';

include_once($path_to_root.'/includes/db_pager.inc');
include_once($path_to_root.'/includes/session.inc');
add_access_extensions();

include_once($path_to_root.'/includes/ui.inc');
include_once($path_to_root.'/modules/FrontHrm/includes/frontHrm_db.inc');
include_once($path_to_root.'/modules/FrontHrm/includes/frontHrm_ui.inc');
include_once($path_to_root.'/reporting/includes/reporting.inc');

// Enable error reporting to debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

$js = '';
if ($SysPrefs->use_popup_windows)
	$js .= get_js_open_window(900, 500);
if (user_use_date_picker())
	$js .= get_js_date_picker();

//--------------------------------------------------------------------------

page(_($help_context = 'Employee Transaction'), isset($_GET['EmpId']), false, '', $js);

if (isset($_GET['EmpId']))
	$_POST['EmpId'] = $_GET['EmpId'];

$days_no = date_diff2(begin_fiscalyear(), Today(), 'd');

start_form();

start_table(TABLESTYLE_NOBORDER);
start_row();

ref_cells(_('Reference:'), 'Ref', _('Enter reference fragment or leave empty'), null, null, true);
ref_cells(_('Memo:'), 'Memo', _('Enter memo fragment or leave empty'), null, null, true);
date_cells(_('From:'), 'FromDate', '', null, $days_no, 0, 0, null, true);
date_cells(_('To:'), 'ToDate', '', null, 0, 0, 0, null, true);

end_row();

start_row();

department_list_cells(null, 'DeptId', null, _('All departments'), true);
employee_list_cells(null, 'EmpId', null, _('All employees'), true, false, get_post('DeptId'));
check_cells(_('Only unpaid:'), 'OnlyUnpaid', null, true);
submit_cells('Search', _('Search'), '', '', 'default');

end_row();
end_table(1);
	
//--------------------------------------------------------------------------

// DEBUG: Function to see what's in $row
function debug_row($row) {
    echo "<div style='background: #f0f0f0; padding: 5px; border: 1px solid #ccc; font-size: 10px;'>";
    if (is_array($row)) {
        foreach ($row as $key => $value) {
            echo "<strong>$key</strong>: " . htmlspecialchars($value) . "<br>";
        }
    } else {
        echo "Not an array: " . gettype($row);
    }
    echo "</div>";
    return '';
}

function check_overdue($row) {
    return '';
}

function trans_type($row) {
    // Try associative access first
    if (is_array($row)) {
        if(isset($row['trans_type'])) {
            if($row['trans_type'] == 1) return _('Bank Payment');
            if($row['trans_type'] == 2) return _('Bank Deposit');
        }
        
        if(isset($row['Type'])) {
            if($row['Type'] == 0) return _('Payslip');
            if(isset($row['payslip_no']) && $row['payslip_no'] == 0) return _('Employee advance');
            return _('Payment advice');
        }
    }
    return '';
}

function view_link($row) {
    global $path_to_root;
    
    if (is_array($row) && isset($row['trans_no']) && $row['trans_no'] != 0) {
        $type = 0;
        if(isset($row['trans_type']) && ($row['trans_type'] == 1 || $row['trans_type'] == 2)) {
            $type = $row['trans_type'];
        } elseif(isset($row['Type'])) {
            $type = $row['Type'];
        }
        
        $link = get_trans_view_str($type, $row['trans_no']);
        if(!empty($link) && $link != $row['trans_no']) {
            return $link;
        }
        
        return "<a href='{$path_to_root}/gl/view/gl_trans_view.php?type={$type}&trans_no={$row['trans_no']}' target='_blank'>{$row['trans_no']}</a>";
    }
    return '';
}

function prt_link($row) {
    if (is_array($row) && isset($row['Type']) && $row['Type'] == 1 && isset($row['payslip_no']) && $row['payslip_no'] != 0) {
        return hrm_print_link($row['payslip_no'], _('Print this Payslip'), ST_PAYSLIP, ICON_PRINT, '', '', 0);
    }
    return '';
}

// Get the SQL query
$sql = get_sql_for_payslips(get_post('FromDate'), get_post('ToDate'), get_post('Ref'), get_post('Memo'), get_post('DeptId'), get_post('EmpId'), check_value('OnlyUnpaid'));

// First test with JUST debug column
echo "<div style='background: yellow; padding: 10px; margin: 10px;'>";
echo "<h3>DEBUG: Testing Data Return</h3>";
$result = db_query($sql, "Test query");
$num_rows = db_num_rows($result);
echo "Rows returned: <strong>$num_rows</strong><br>";

if ($num_rows > 0) {
    $test_row = db_fetch_assoc($result);
    echo "<pre>";
    print_r($test_row);
    echo "</pre>";
}
echo "</div>";

// Define columns - SIMPLE ASSOCIATIVE KEYS
$cols = array(
    _('Debug') => array('fun'=>'debug_row'),       // DEBUG FIRST
    _('Date') => 'generated_date',                // Use column name
    _('Trans #') => array('fun'=>'view_link'),    // Function
    _('Type') => array('fun'=>'trans_type'),      // Function
    _('Employee ID') => 'emp_id',                 // Use column name
    _('Employee Name') => 'emp_name',             // Use column name
    _('Payslip No') => 'payslip_no',              // Use column name
    _('Pay from') => 'from_date',                 // Use column name
    _('Pay to') => 'to_date',                     // Use column name
    _('Amount') => 'payable_amount',              // Use column name
    '' => array('align'=>'center', 'fun'=>'prt_link')
);





$table =& new_db_pager('trans_tbl', $sql, $cols, null, null, 15);
$table->set_marker('check_overdue', _('Marked items are overdue.'));
$table->width = '80%';

display_db_pager($table);

end_form();
end_page();

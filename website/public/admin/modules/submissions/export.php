<?php
/**
 * CSV export of the submissions list.
 *
 * Takes the same q / form_type / status / sort parameters as index.php and
 * reuses the same listing helpers to build the query, so "export" always means
 * "export exactly what I am looking at" — the alternative, a second set of
 * filter controls on an export screen, is two filters to keep in step and a
 * reliable source of "why is this file different from the page".
 *
 * Pagination is the one parameter deliberately ignored: an export is the whole
 * matching set, not the twenty rows currently on screen.
 *
 * Gated on submissions.view rather than a stricter capability because it
 * reveals nothing the list page does not already show to the same person. It
 * is logged, though — pulling every enquirer's name and email address into a
 * file that then leaves the building is worth a line in the audit trail even
 * when it is entirely routine.
 */

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/listing.php';
require_once __DIR__ . '/../../includes/trash.php';
require_once __DIR__ . '/../../../../includes/form-submissions.php';

requireCapability('submissions.view', '../../dashboard.php');

// Rows are streamed in batches rather than fetched in one array: this table
// grows on its own, without anyone in the CMS adding to it, so the export is
// the one query here whose result set has no natural ceiling.
const EXPORT_BATCH = 500;

/**
 * Every column is qualified with the `s.` alias, unlike index.php's copy.
 *
 * This query joins admin_users to resolve handled_by into a name, and the two
 * tables share `name` and `created_at`. Unqualified, the sort and the search
 * would both be ambiguous — MySQL rejects the query outright, so the export
 * would fail for everyone the moment it shipped rather than quietly returning
 * the wrong rows. The keys stay identical to index.php's so a URL built by the
 * list page's sort headers means the same thing here.
 */
$sortable = [
    'received' => 's.created_at',
    'name' => 's.name',
    'email' => 's.email',
    'form' => 's.form_type',
    'status' => 's.status',
];

$params = listParams($sortable, 'received', ['form_type', 'status']);
if (!isset($_GET['dir'])) {
    $params['dir'] = 'DESC';
}

$where = listWhere(
    $params,
    ['s.name', 's.email', 's.subject', 's.message'],
    ['form_type' => 's.form_type', 'status' => 's.status'],
    [notTrashed('s')]
);

$fromSql = ' FROM form_submissions s
             LEFT JOIN admin_users u ON u.id = s.handled_by' . $where['sql'];

$total = (int) dbFetchColumn('SELECT COUNT(*)' . $fromSql, $where['params'], 0);

logActivity(
    currentUser()['id'],
    'export',
    'submissions',
    sprintf('Exported %d submission(s) to CSV', $total)
);

/**
 * Neutralises spreadsheet formula injection.
 *
 * name, subject and message are typed by anonymous members of the public. A
 * cell whose text begins with =, +, - or @ is treated by Excel, LibreOffice
 * and Google Sheets as a formula, so a visitor could put
 * =HYPERLINK(...) — or a command-executing DDE payload — into the message box
 * and have it run on the machine of whoever at WEF opens the export. Quoting
 * the value for CSV does not prevent this; the spreadsheet un-quotes it and
 * then evaluates what is inside.
 *
 * Prefixing with an apostrophe is the standard fix: spreadsheets read it as
 * "treat the rest as literal text" and do not display it. A leading tab is
 * stripped for the same reason it would otherwise be added — it is one of the
 * characters that can re-trigger the parse.
 */
function csvSafe(?string $value): string
{
    $value = (string) $value;

    if ($value !== '' && str_contains("=+-@\t\r", $value[0])) {
        return "'" . $value;
    }

    return $value;
}

// Anything already emitted (a stray warning, the error handler's buffer) would
// end up as the first bytes of the file and corrupt it.
while (ob_get_level() > 0) {
    ob_end_clean();
}

$filename = 'wef-submissions-' . date('Y-m-d') . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store');
// Nothing is HTML here; without this a browser that sniffs could still decide
// otherwise.
header('X-Content-Type-Options: nosniff');

$out = fopen('php://output', 'w');

// UTF-8 BOM. Excel on Windows assumes the system codepage for a .csv without
// one, which turns every accented name and every non-Latin character in a
// message into mojibake — the exact data this export exists to preserve.
fwrite($out, "\xEF\xBB\xBF");

fputcsv($out, [
    'ID',
    'Received',
    'Form',
    'Name',
    'Email',
    'Subject',
    'Message',
    'Status',
    'Handled by',
    'Handled at',
    'Internal note',
    'IP address',
]);

// sortSql comes from the $sortable allow-list above, never from the request —
// the same rule the list pages follow, and the reason ORDER BY can be
// interpolated here at all.
$sql = 'SELECT s.id, s.form_type, s.name, s.email, s.subject, s.message, s.status,
               s.admin_note, s.ip_address, s.created_at, s.handled_at,
               u.name AS handled_by_name'
    . $fromSql
    . ' ORDER BY ' . $params['sortSql'] . ' ' . $params['dir'] . ', s.id DESC';

$offset = 0;
do {
    $batch = dbFetchAll(
        $sql . ' LIMIT ' . EXPORT_BATCH . ' OFFSET ' . (int) $offset,
        $where['params']
    );

    foreach ($batch as $row) {
        fputcsv($out, [
            $row['id'],
            date('Y-m-d H:i:s', strtotime($row['created_at'])),
            submissionFormLabel($row['form_type']),
            csvSafe($row['name']),
            csvSafe($row['email']),
            csvSafe(submissionSubjectLabel($row['form_type'], $row['subject'])),
            csvSafe($row['message']),
            submissionStatusLabel($row['status']),
            csvSafe($row['handled_by_name']),
            $row['handled_at'] === null ? '' : date('Y-m-d H:i:s', strtotime($row['handled_at'])),
            csvSafe($row['admin_note']),
            $row['ip_address'],
        ]);
    }

    // Flushed per batch so a large export starts downloading immediately
    // instead of sitting in memory until the last row is read.
    flush();
    $offset += EXPORT_BATCH;
} while (count($batch) === EXPORT_BATCH);

fclose($out);
exit;

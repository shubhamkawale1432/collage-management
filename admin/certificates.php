<?php
require_once __DIR__.'/../includes/bootstrap.php';
require_perm('certificates.manage');
check_csrf();

$allowedTypes=['Bonafide Certificate','Character Certificate','Course Completion','Achievement','Participation','Internship'];

if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        $sid=(int)($_POST['student_id']??0);
        $type=trim($_POST['type']??'');

        if($sid<=0) throw new RuntimeException('Please select a valid student.');
        if(!in_array($type,$allowedTypes,true)) throw new RuntimeException('Invalid certificate type.');

        // Validate the foreign-key parent before attempting the INSERT. This also
        // protects against tampered POST data and produces a useful message.
        $studentCheck=db()->prepare('SELECT id FROM students WHERE id=? LIMIT 1');
        $studentCheck->execute([$sid]);
        if(!$studentCheck->fetchColumn()){
            throw new RuntimeException('The selected student does not exist. Refresh the page and select an existing student.');
        }

        $issuerCheck=db()->prepare('SELECT id FROM users WHERE id=? LIMIT 1');
        $issuerCheck->execute([actor_id()]);
        if(!$issuerCheck->fetchColumn()) throw new RuntimeException('Invalid issuing account.');

        $no='CERT-'.date('Y').'-'.strtoupper(substr(hash('sha256',secure_random(8).microtime(true)),0,12));
        $code='VERIFY-'.strtoupper(substr(hash('sha256',$no.secure_random(4)),0,18));

        db()->beginTransaction();
        $q=db()->prepare("INSERT INTO certificates(student_id,type,certificate_no,issue_date,status,issuer_user_id) VALUES(?,?,?,CURDATE(),'Issued',?)");
        $q->execute([$sid,$type,$no,actor_id()]);
        $cid=(int)db()->lastInsertId();

        db()->prepare('INSERT INTO certificate_verifications(certificate_id,verification_code) VALUES(?,?)')->execute([$cid,$code]);
        db()->commit();

        AuditService::log('CERTIFICATE_ISSUE','certificates',$cid,$no);
        $_SESSION['flash']=['success','Issued '.$no.' · Verification code '.$code];
    }catch(Throwable $e){
        if(db()->inTransaction()) db()->rollBack();
        $_SESSION['flash']=['danger',$e->getMessage()];
    }
    redirect('certificates.php');
}

$students=db()->query('SELECT s.id,u.name,s.enrollment_no FROM students s JOIN users u ON u.id=s.user_id ORDER BY u.name')->fetchAll();
$rows=db()->query('SELECT c.*,u.name student_name,v.verification_code FROM certificates c JOIN students s ON s.id=c.student_id JOIN users u ON u.id=s.user_id LEFT JOIN certificate_verifications v ON v.certificate_id=c.id ORDER BY c.id DESC')->fetchAll();

page_top('Digital Certificate Center');
flash();
?>
<div class='card p-4 mb-3'>
    <h5>Issue Certificate</h5>
    <?php if(!$students): ?>
        <div class='alert alert-warning mb-0'>No students are available. Create a student first.</div>
    <?php else: ?>
    <form method='post' class='row g-3'>
        <input type='hidden' name='csrf' value='<?=e(csrf_token())?>'>
        <div class='col-md-5'>
            <label class='form-label'>Student</label>
            <select name='student_id' class='form-select' required>
                <option value=''>Select student</option>
                <?php foreach($students as $s): ?>
                    <option value='<?=e($s['id'])?>'><?=e($s['name'].' · '.$s['enrollment_no'])?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class='col-md-5'>
            <label class='form-label'>Certificate Type</label>
            <select name='type' class='form-select' required>
                <?php foreach($allowedTypes as $t): ?><option><?=e($t)?></option><?php endforeach; ?>
            </select>
        </div>
        <div class='col-md-2 d-flex align-items-end'>
            <button class='btn btn-primary w-100'>Issue</button>
        </div>
    </form>
    <?php endif; ?>
</div>
<div class='card p-3 table-responsive'>
    <table class='table'>
        <tr><th>Student</th><th>Certificate</th><th>Number</th><th>Status</th><th>Verification</th></tr>
        <?php foreach($rows as $r): ?>
        <tr>
            <td><?=e($r['student_name'])?></td>
            <td><?=e($r['type'])?></td>
            <td><?=e($r['certificate_no'])?></td>
            <td><?=e($r['status'])?></td>
            <td><code><?=e($r['verification_code']??'')?></code> · <a target='_blank' href='../public/verification.php?code=<?=urlencode($r['verification_code']??'')?>'>Verify</a></td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>
<?php page_bottom();

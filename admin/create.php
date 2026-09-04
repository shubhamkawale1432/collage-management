<?php
require_once __DIR__.'/../includes/bootstrap.php';
require_login(); check_csrf();
$map=require __DIR__.'/entity_map.php';
$e=$_GET['e']??$_POST['e']??''; if(!isset($map[$e])) exit('Invalid entity');
require_perm($map[$e]['perm']);
$id=(int)($_GET['id']??$_POST['id']??0); $m=$map[$e];
$defs=[];
$defs['users']=[['name','text'],['email','email'],['role_id','number'],['password_hash','text'],['status','select:active|inactive|suspended']];
$defs['departments']=[['name','text'],['code','text'],['description','textarea'],['status','select:1|0']];
$defs['courses']=[['name','text'],['code','text'],['duration_years','number'],['status','select:1|0']];
$defs['branches']=[['department_id','number'],['name','text'],['code','text'],['intake','number'],['status','select:1|0']];
$defs['academic_years']=[['label','text'],['is_current','select:1|0']];
$defs['semesters']=[['number','number'],['name','text']];
$defs['divisions']=[['name','text']];
$defs['subjects']=[['code','text'],['name','text'],['branch_id','number'],['semester_id','number'],['credits','number'],['subject_type','text'],['max_marks','number'],['passing_marks','number'],['status','select:1|0']];
$defs['applications']=[['applicant_user_id','number'],['application_type','text'],['title','text'],['description','textarea'],['status','select:Draft|Submitted|Under Verification|Correction Required|Approved|Rejected|Waitlisted|Admitted'],['owner_role','text'],['current_stage','text']];
$defs['exams']=[['name','text'],['type','text'],['academic_year_id','number'],['semester_id','number'],['branch_id','number'],['exam_date','date'],['start_time','time'],['end_time','time'],['room','text'],['max_marks','number'],['status','select:scheduled|cancelled|completed']];
$defs['certificates']=[['student_id','number'],['type','text'],['certificate_no','text'],['issue_date','date'],['status','select:Issued|Revoked|Draft']];
$defs['books']=[['isbn','text'],['title','text'],['author','text'],['publisher','text'],['category','text'],['year','number'],['status','select:Active|Inactive']];
$defs['admissions']=[['application_no','text'],['full_name','text'],['email','email'],['program','text'],['branch','text'],['status','select:Draft|Submitted|Under Verification|Correction Required|Verified|Approved|Rejected|Waitlisted|Admitted']];
$defs['assignment_list']=[['subject_id','number'],['faculty_id','number'],['title','text'],['description','textarea'],['deadline','datetime-local'],['max_marks','number'],['status','select:published|draft|closed']];
$defs['materials']=[['subject_id','number'],['faculty_id','number'],['title','text'],['file_path','text'],['mime_type','text'],['file_size','number']];
$defs['timetables']=[['day_name','text'],['start_time','time'],['end_time','time'],['subject_id','number'],['faculty_id','number'],['branch_id','number'],['semester_id','number'],['division_id','number'],['room','text'],['academic_year_id','number']];
$defs['alumni']=[['student_id','number'],['graduation_year','number'],['status','text'],['current_company','text'],['designation','text'],['contact_email','email'],['contact_mobile','text'],['profile_url','url'],['notes','textarea']];
$fields=$defs[$e]??[];
if(!$fields){foreach($m['columns'] as $c){if($c==='id')continue;$fields[]=[$c,'text'];}}
$data=[];
if($id){$q=db()->prepare("SELECT * FROM `{$m['table']}` WHERE id=?");$q->execute([$id]);$data=$q->fetch()?:[];}
if($_SERVER['REQUEST_METHOD']==='POST'){
  try{
    $names=[];$vals=[];
    foreach($fields as $field){$n=$field[0];$names[]=$n;$vals[]=$_POST[$n]??null;}
    if($e==='users' && empty($_POST['password_hash']) && !$id) throw new RuntimeException('Use a password in password_hash field for seeded-style admin user creation. Prefer the Identity page for normal users.');
    if($id){$sets=[];foreach($names as $n)$sets[]="`$n`=?";$q=db()->prepare("UPDATE `{$m['table']}` SET ".implode(',',$sets).' WHERE id=?');$q->execute(array_merge($vals,[$id]));AuditService::log('UPDATE',$e,$id,'Entity update');}
    else{$cols='`'.implode('`,`',$names).'`';$ph=implode(',',array_fill(0,count($names),'?'));$q=db()->prepare("INSERT INTO `{$m['table']}` ({$cols}) VALUES ({$ph})");$q->execute($vals);$id=(int)db()->lastInsertId();AuditService::log('CREATE',$e,$id,'Entity create');}
    $_SESSION['flash']=['success','Record saved'];redirect('entity.php?e='.urlencode($e));
  }catch(Throwable $ex){$err=$ex->getMessage();}
}
page_top(($id?'Edit ':'Create ').$m['label']);flash();
?>
<div class="card p-4">
<?php if(!empty($err)): ?><div class="alert alert-danger"><?=e($err)?></div><?php endif; ?>
<form method="post" class="row g-3">
<input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="e" value="<?=e($e)?>"><input type="hidden" name="id" value="<?=e($id)?>">
<?php foreach($fields as $field): $n=$field[0];$t=$field[1]; ?>
<div class="col-md-6"><label class="form-label"><?=e(ucwords(str_replace('_',' ',$n)))?></label>
<?php if(str_starts_with($t,'select:')): $opts=explode('|',substr($t,7)); ?><select name="<?=e($n)?>" class="form-select"><?php foreach($opts as $o): ?><option value="<?=e($o)?>" <?=((string)($data[$n]??'')===(string)$o?'selected':'')?>><?=e($o)?></option><?php endforeach; ?></select>
<?php elseif($t==='textarea'): ?><textarea name="<?=e($n)?>" rows="4" class="form-control"><?=e($data[$n]??'')?></textarea>
<?php else: ?><input class="form-control" type="<?=e($t)?>" name="<?=e($n)?>" value="<?=e($data[$n]??'')?>"><?php endif; ?>
</div><?php endforeach; ?>
<div class="col-12"><button class="btn btn-primary">Save</button> <a class="btn btn-outline-secondary" href="entity.php?e=<?=urlencode($e)?>">Cancel</a></div>
</form></div>
<?php page_bottom(); ?>
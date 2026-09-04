<?php
class AIService{
 public static function answer($q){$u=current_user();$q0=strtolower(trim($q));if(!$u)return 'Please sign in.';
  if(str_contains($q0,'attendance')){if(($u['role_name']??'')==='Student'){ $s=db()->prepare('SELECT ROUND(100*SUM(status="Present")/NULLIF(COUNT(*),0),2) FROM attendance a JOIN students st ON st.id=a.student_id WHERE st.user_id=?');$s->execute([$u['id']]);$v=$s->fetchColumn();return 'Your recorded attendance is '.($v===null?'not available':$v.'%').'.'; } return 'Attendance analytics are available from your authorized dashboard.';}
  if(str_contains($q0,'fee')||str_contains($q0,'fees')){if(($u['role_name']??'')==='Student'){ $s=db()->prepare('SELECT COALESCE(SUM(amount-paid_amount),0) FROM student_fees sf JOIN students st ON st.id=sf.student_id WHERE st.user_id=?');$s->execute([$u['id']]);return 'Your current fee balance is ₹'.number_format((float)$s->fetchColumn(),2).'.';}return 'Use the Accounts dashboard for institution-level fee analysis.';}
  if(str_contains($q0,'exam')){ $s=db()->query("SELECT COUNT(*) FROM exams WHERE exam_date>=CURDATE() AND status='scheduled'");return 'There are '.$s->fetchColumn().' upcoming scheduled examinations in the system.';}
  if(str_contains($q0,'student')){if(in_array($u['role_name'],['Super Admin','College Admin','Principal'],true)){return 'The college currently has '.db()->query('SELECT COUNT(*) FROM students')->fetchColumn().' student records.';}return 'Student privacy rules limit this query to your authorized scope.';}
  return 'I can answer authorized questions about attendance, fees, examinations, students, results, applications and campus services. Try: “What is my attendance?”';
 }
}

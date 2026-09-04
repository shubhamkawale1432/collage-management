<?php
class GradeService{public static function grade($percent){$q=db()->prepare('SELECT grade,grade_point FROM grades WHERE ? BETWEEN min_percent AND max_percent ORDER BY min_percent DESC LIMIT 1');$q->execute([$percent]);return $q->fetch()?:['grade'=>'F','grade_point'=>0];}}

<?php
use yii\helpers\Html;
?>
Hello <?=Html::encode($name)?>,
<p>
    This is a test message you have received as a result of someone running the
    cron/test-emails action.  This is a test URL:
    <a href="<?=$crashPlanUrl?>" alt="TestURL"><?=$crashPlanUrl?></a> 
    This email can be ignored.
</p>

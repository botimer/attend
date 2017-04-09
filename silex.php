<?php
require_once "../config.php";

use \Tsugi\Core\LTIX;
use \Tsugi\Core\Settings;
use \Tsugi\Util\Net;

$launch = LTIX::requireData();
// if ( count($_POST) > 0 ) { var_dump($launch); die(); }
$app = new \Tsugi\Silex\Application($launch);
$app['debug'] = true;

$app->get('/', function (\Silex\Application $app) use ($CFG) {
    global $USER, $PDOX, $LINK;
    $context = array();
    $context['old_code'] = Settings::linkGet('code', '');

    $p = $CFG->dbprefix;
    if ( $USER->instructor ) {
        $rows = $PDOX->allRowsDie("SELECT user_id,attend,ipaddr FROM {$p}attend
                WHERE link_id = :LI ORDER BY attend DESC, user_id",
                array(':LI' => $LINK->id)
        );
        $context['rows'] = $rows;
    }
    return $app['twig']->render('Attend.twig', $context);
})->bind('main');

$app->post('/', function (\Silex\Application $app) use ($CFG) {
    global $USER, $PDOX, $LINK;
    $p = $CFG->dbprefix;
    $old_code = Settings::linkGet('code', '');
    if ( isset($_POST['code']) && isset($_POST['set']) && $USER->instructor ) {
        Settings::linkSet('code', $_POST['code']);
        $app->tsugiFlashSuccess('Code updated');
    } else if ( isset($_POST['clear']) && $USER->instructor ) {
        $rows = $PDOX->queryDie("DELETE FROM {$p}attend WHERE link_id = :LI",
                array(':LI' => $LINK->id)
        );
        $app->tsugiFlashSuccess('Data cleared');
    } else if ( isset($_POST['code']) ) { // Student
        if ( $old_code == $_POST['code'] ) {
            $PDOX->queryDie("INSERT INTO {$p}attend
                (link_id, user_id, ipaddr, attend, updated_at)
                VALUES ( :LI, :UI, :IP, NOW(), NOW() )
                ON DUPLICATE KEY UPDATE updated_at = NOW()",
                array(
                    ':LI' => $LINK->id,
                    ':UI' => $USER->id,
                    ':IP' => Net::getIP()
                )
            );
            $app->tsugiFlashSuccess(__('Attendance Recorded...'));
        } else {
            $app->tsugiFlashSuccess(__('Code incorrect'));
        }
    }
    return $app->tsugiRedirect('main');
});

$app->run();

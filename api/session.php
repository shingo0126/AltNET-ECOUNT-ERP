<?php
/**
 * Session management API
 */
if (!Auth::check()) {
    jsonResponse(['error' => 'Not authenticated'], 401);
}

$action = getParam('action', '');

if ($action === 'extend') {
    Session::set('last_activity', time());
    jsonResponse(['success' => true, 'remaining' => 1800]);
}

if ($action === 'check') {
    $info = Session::getSessionInfo();
    jsonResponse($info);
}

jsonResponse(['error' => 'Unknown action'], 400);

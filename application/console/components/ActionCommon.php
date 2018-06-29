<?php
namespace console\components;

class ActionCommon
{

    function try_lock($tokenSemaphore, $tokenValue) {
        sem_acquire($tokenSemaphore);
        if (!shm_has_var($tokenValue, 6)) {
            shm_put_var($tokenValue, 6, 0);
        }
        $tmp = shm_get_var($tokenValue, 6);
        // This is so that if a crash occurs, it won't hang up for more than
        // 20 minutes.  A single instance should never take more than 20 minutes
        // to run
        if ($tmp > 20) {
            $tmp = 0;
        }
        $exit = ($tmp > 0);
        $tmp = $tmp + 1;
        $tmp = shm_put_var($tokenValue, 6, $tmp);
        $tmp = shm_get_var($tokenValue, 6);
        sem_release($tokenSemaphore);
        if ($exit) return false;
        return true;
    }
    function release($tokenSemaphore, $tokenValue) {
        sem_acquire($tokenSemaphore);
        $tmp = shm_get_var($tokenValue, 6);
        $tmp = shm_put_var($tokenValue, 6, 0);
        $tmp = shm_get_var($tokenValue, 6);
        sem_release($tokenSemaphore);
    }
}



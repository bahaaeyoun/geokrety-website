<?php

namespace GeoKrety\Controller\Admin;

use GeoKrety\Controller\Admin\Traits\ScriptLoader;
use GeoKrety\Controller\Base;
use GeoKrety\Service\Smarty;

class ScriptsAck extends Base {
    use ScriptLoader;

    public function get() {
        Smarty::render('dialog/admin_dialog_script_ack.tpl');
    }

    public function post(\Base $f3) {
        $this->script->touch('acked_on_datetime');
        $this->script->save();
        \Flash::instance()->addMessage(sprintf(_('Script "%s" has been acked'), $this->script->name), 'success');
        \Sugar\Event::instance()->emit('scripts.acked', $this->script);
        $f3->reroute('@admin_scripts');
    }
}

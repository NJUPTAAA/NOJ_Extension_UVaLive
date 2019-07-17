<?php
namespace App\Babel\Extension\uvalive;

use App\Babel\Install\InstallerBase;
use Exception;

class Installer extends InstallerBase
{
    public $ocode="uvalive";

    public function install()
    {
        // throw new Exception("No Install Method Provided");
        $this->_install($this->ocode);
    }

    public function uninstall()
    {
        // throw new Exception("No Uninstall Method Provided");
        $this->_uninstall($this->ocode);
    }
}

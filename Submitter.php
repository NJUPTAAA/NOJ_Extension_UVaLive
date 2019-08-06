<?php
namespace App\Babel\Extension\uvalive;

use App\Babel\Submit\Curl;
use App\Models\CompilerModel;
use App\Models\JudgerModel;
use App\Models\OJModel;
use Illuminate\Support\Facades\Validator;
use Requests;

class Submitter extends Curl
{
    protected $sub;
    public $post_data = [];
    protected $oid;
    protected $selectedJudger;

    public function __construct(&$sub, $all_data)
    {
        $this->sub = &$sub;
        $this->post_data = $all_data;
        $judger = new JudgerModel();
        $this->oid = OJModel::oid('uvalive');
        if (is_null($this->oid)) {
            throw new Exception("Online Judge Not Found");
        }
        $judger_list = $judger->list($this->oid);
        $this->selectedJudger = $judger_list[array_rand($judger_list)];
    }

    private function _login()
    {
        $response = $this->grab_page([
            'site' => "https://icpcarchive.ecs.baylor.edu/",
            'oj' => 'uvalive',
            'handle' => $this->selectedJudger['handle'],
        ]);
        if (strpos($response, 'Logout') === false) {
            $post_data = [
                'username' => $this->selectedJudger["handle"],
                'passwd' => $this->selectedJudger["password"],
                'remember' => 'yes',
            ];
            $inputs = preg_match_all('/<input type="\w*" name="(op2|lang|force_session|return|message|loginfrom|cbsecuritym3|\w[0-9a-z]{32})" value="(.*?)" \/>/', $response, $matches);
            for ($i = 0; $i < $inputs; ++$i) {
                $post_data[$matches[1][$i]] = $matches[2][$i];
            }
            $this->post_data([
                'site' => 'https://icpcarchive.ecs.baylor.edu/index.php?option=com_comprofiler&task=login',
                'data' => $post_data,
                'oj' => 'uvalive',
                'ret' => false,
                'handle' => $this->selectedJudger['handle'],
            ]);
        }
    }

    private function _submit()
    {
        $params = [
            'problemid' => $this->post_data['iid'],
            'language' => $this->post_data['lang'],
            'code' => $this->post_data['solution'],
        ];

        $response = $this->post_data([
            'site' => "https://icpcarchive.ecs.baylor.edu/index.php?option=com_onlinejudge&Itemid=25&page=save_submission",
            'data' => $params,
            'oj' => 'uvalive',
            'ret' => true,
            'returnHeader' => true,
            'handle' => $this->selectedJudger['handle'],
        ]);
        $this->sub['jid'] = $this->selectedJudger["jid"];
        \Log::debug($response);
        if (preg_match('/Submission\+received\+with\+ID\+(\d+)/', $response, $match)) {
            $this->sub['remote_id'] = $match[1];
        } else {
            $this->sub['verdict'] = 'Submission Error';
        }
    }

    public function submit()
    {
        $validator = Validator::make($this->post_data, [
            'pid' => 'required|integer',
            'coid' => 'required|integer',
            'iid' => 'required|integer',
            'solution' => 'required',
        ]);

        if ($validator->fails()) {
            $this->sub['verdict'] = "System Error";
            return;
        }

        $this->_login();
        $this->_submit();
    }
}

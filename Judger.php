<?php
namespace App\Babel\Extension\uvalive;

use App\Babel\Submit\Curl;
use App\Models\Submission\SubmissionModel;
use App\Models\JudgerModel;
use KubAT\PhpSimple\HtmlDomParser;
use Log;

class Judger extends Curl
{

    public $verdict = [
        "Compilation error" => "Compile Error",
        "Wrong answer" => "Wrong Answer",
        "Accepted" => "Accepted",
        "Time limit exceeded" => "Time Limit Exceed",
        "Runtime error" => "Runtime Error",
        'Submission error' => 'Submission Error',
        "Output limit exceeded" => "Output Limit Exceeded",
        "Memory limit exceed" => "Memory Limit Exceed",
    ];
    private $list = [];
    private $proceedJID = [];
    private $judgerDetails = [];


    public function __construct()
    {
        $this->submissionModel = new SubmissionModel();
        $this->judgerModel = new JudgerModel();
    }

    private function fetchRemoteVerdictList($judgerDetail)
    {
        if(in_array($judgerDetail['jid'], $this->proceedJID)) {
            return true;
        }
        $this->proceedJID[] = $judgerDetail['jid'];
        $response = $this->grab_page([
            'site' => "https://icpcarchive.ecs.baylor.edu/index.php?option=com_onlinejudge&Itemid=9&limit=100&limitstart=0",
            'oj' => 'uvalive',
            'handle' => $judgerDetail['handle'],
        ]);
        $submissionsDOM = HtmlDomParser::str_get_html($response, true, true, DEFAULT_TARGET_CHARSET, false);
        foreach ($submissionsDOM->find('td.maincontent tr') as $verdictDOM) {
            if(in_array($verdictDOM->class, ['sectiontableentry1', 'sectiontableentry2'])) {
                $remoteID = $verdictDOM->find('td', 0)->plaintext;
                $verdict = trim($verdictDOM->find('td', 3)->plaintext);
                $time = trim($verdictDOM->find('td', 5)->plaintext) * 1000;
                $this->list[$remoteID] = [
                    'time' => $time,
                    'verdict' => $verdict
                ];
            }
        }
    }

    private function getJudgerDetails($JID)
    {
        if(!isset($this->judgerDetails[$JID])) {
            $this->judgerDetails[$JID] = $this->judgerModel->detail($JID);
        }
        return $this->judgerDetails[$JID];
    }

    public function judge($row)
    {
        $judgerDetail = $this->getJudgerDetails($row['jid']);
        $this->fetchRemoteVerdictList($judgerDetail);
        if (array_key_exists($row['remote_id'], $this->list)) {
            $sub = [];
            if (!isset($this->verdict[$this->list[$row['remote_id']]['verdict']])) {
                return;
            }
            $sub['verdict'] = $this->verdict[$this->list[$row['remote_id']]['verdict']];
            if ($sub['verdict'] === 'Compile Error') {
                $response = $this->grab_page([
                    'site' => "https://icpcarchive.ecs.baylor.edu/index.php?option=com_onlinejudge&Itemid=9&page=show_compilationerror&submission=$row[remote_id]",
                    'oj' => 'uvalive',
                    'handle' => $judgerDetail['handle'],
                ]);
                if (preg_match('/<pre>([\s\S]*)<\/pre>/', $response, $match)) {
                    $sub['compile_info'] = trim($match[1]);
                }
            }
            $sub['score'] = $sub['verdict'] == "Accepted" ? 1 : 0;
            $sub['remote_id'] = $row['remote_id'];
            $sub['time'] = $this->list[$row['remote_id']]['time'];

            $this->submissionModel->updateSubmission($row['sid'], $sub);
        }
    }
}

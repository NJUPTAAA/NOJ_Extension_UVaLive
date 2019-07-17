<?php
namespace App\Babel\Extension\uva;//The 'template' should be replaced by the real oj code.

use App\Babel\Crawl\CrawlerBase;
use App\Models\ProblemModel;
use App\Models\OJModel;
use KubAT\PhpSimple\HtmlDomParser;
use Requests;
use Exception;

class Crawler extends CrawlerBase
{
    public $oid=null;
    public $prefix="UVa";
    private $con;
    private $imgi;
    /**
     * Initial
     *
     * @return Response
     */
    public function __construct($conf)
    {
        $action=isset($conf["action"])?$conf["action"]:'crawl_problem';
        $con=isset($conf["con"])?$conf["con"]:'all';
        $cached=isset($conf["cached"])?$conf["cached"]:false;
        $this->oid=OJModel::oid('uva');

        if(is_null($this->oid)) {
            throw new Exception("Online Judge Not Found");
        }

        if ($action=='judge_level') {
            $this->judge_level();
        } else {
            $this->crawl($con);
        }
    }

    public function judge_level()
    {
        // TODO
    }

    public function crawl($con)
    {
        $problemModel=new ProblemModel();
        if ($con=='all') {
            $res=Requests::get("https://uhunt.onlinejudge.org/api/p");
            $result=json_decode($res->body, true);
            $info=[];
            for ($i=0; $i<count($result); ++$i) {
                $info[$result[$i][1]]=[$result[$i][0], $result[$i][2], $result[$i][3], $result[$i][19]];
            }
            ksort($info);
            foreach ($info as $key=>$value) {
                $this->pro['pcode']='UVA'.$key;
                $this->pro['OJ']=$this->oid;
                $this->pro['contest_id']=null;
                $this->pro['index_id']=$value[0];
                $this->pro['origin']="https://uva.onlinejudge.org/index.php?option=com_onlinejudge&Itemid=8&page=show_problem&problem=".$value[0];
                $this->pro['title']=$value[1];
                $this->pro['time_limit']=$value[3];
                $this->pro['memory_limit']=131072; // Given in elder codes
                $this->pro['solved_count']=$value[2];
                $this->pro['input_type']='standard input';
                $this->pro['output_type']='standard output';
                $this->pro['description']="<a href=\"/external/gym/UVa{$key}.pdf\">[Attachment Link]</a>";
                $this->pro['input']='';
                $this->pro['output']='';
                $this->pro['note']='';
                $this->pro['sample']=[];
                $this->pro['source']='Here';
                $this->pro['file']=1;

                $problem=$problemModel->pid($this->pro['pcode']);

                if ($problem) {
                    $problemModel->clearTags($problem);
                    $new_pid=$this->update_problem($this->oid);
                } else {
                    $new_pid=$this->insert_problem($this->oid);
                }

                // $problemModel->addTags($new_pid, $tag); // not present
            }
            $this->data=array_keys($info);
        } else {
            $pf=substr($con, 0, strlen($con)-2);
            $res=Requests::get("https://uva.onlinejudge.org/external/$pf/p$con.pdf");
            file_put_contents(base_path("public/external/gym/UVa$con.pdf"), $res->body);
        }
    }
}

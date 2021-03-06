<?php
/**************************************************************************
**********      English Wikipedia Account Request Interface      **********
***************************************************************************
** Wikipedia Account Request Graphic Design by Charles Melbye,           **
** which is licensed under a Creative Commons                            **
** Attribution-Noncommercial-Share Alike 3.0 United States License.      **
**                                                                       **
** All other code are released under the Public Domain                   **
** by the ACC Development Team.                                          **
**                                                                       **
** See CREDITS for the list of developers.                               **
***************************************************************************/

class StatsMonthlyStats extends StatisticsPage
{
    protected function execute()
    {
        $qb = new QueryBrowser();

        $query = "SELECT COUNT(DISTINCT log_id) AS 'Requests Closed', YEAR(log_time) AS 'Year', MONTHNAME(log_time) AS 'Month' FROM acc_log WHERE log_action LIKE 'Closed%' GROUP BY EXTRACT(YEAR_MONTH FROM log_time) ORDER BY YEAR(log_time), MONTH(log_time) ASC;";

        $out = $qb->executeQueryToTable($query);

        global $showGraphs;
        if($showGraphs == 1)
        {
            global $filepath;
            require_once($filepath . 'graph/pChart/pChart.class');
            require_once($filepath . 'graph/pChart/pData.class');

            $queries = array();

            $queries[] = array(
                    'query' => "SELECT COUNT(DISTINCT log_id) AS 'y', CONCAT( YEAR(log_time), '/' , MONTHNAME(log_time)) AS 'x' FROM acc_log WHERE log_action LIKE 'Closed%' AND YEAR(log_time) != 0 GROUP BY EXTRACT(YEAR_MONTH FROM log_time) ORDER BY YEAR(log_time), MONTH(log_time) ASC;",
                    'series' => "All closed requests by month"
                );
            $queries[] = array(
                    'query' => "SELECT COUNT(DISTINCT log_id) AS 'y', CONCAT( YEAR(log_time), '/' , MONTHNAME(log_time)) AS 'x' FROM acc_log WHERE log_action LIKE 'Closed 0' AND YEAR(log_time) != 0 GROUP BY EXTRACT(YEAR_MONTH FROM log_time) ORDER BY YEAR(log_time), MONTH(log_time) ASC;",
                    'series' => "Dropped requests by month"
                );

            $query = gGetDb()->query("SELECT id, name FROM emailtemplate WHERE active = '1';");
            if (!$query)
            {
                die("Query error.");
            }

            foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row)
            {
                $id = $row['id'];
                $name = $row['name'];
                $queries[] = array(
                    'query' => "SELECT COUNT(DISTINCT log_id) AS 'y', CONCAT( YEAR(log_time), '/' , MONTHNAME(log_time)) AS 'x' FROM acc_log WHERE log_action LIKE 'Closed $id' AND YEAR(log_time) != 0 GROUP BY EXTRACT(YEAR_MONTH FROM log_time) ORDER BY YEAR(log_time), MONTH(log_time) ASC;",
                    'series' => "$name requests by month"
                );
            }

            $queries[] = array(
                    'query' => "SELECT COUNT(DISTINCT log_id) AS 'y', CONCAT( YEAR(log_time), '/' , MONTHNAME(log_time)) AS 'x' FROM acc_log WHERE log_action LIKE 'Closed custom-y' AND YEAR(log_time) != 0 GROUP BY EXTRACT(YEAR_MONTH FROM log_time) ORDER BY YEAR(log_time), MONTH(log_time) ASC;",
                    'series' => "Custom created requests by month"
                );
            $queries[] = array(
                    'query' => "SELECT COUNT(DISTINCT log_id) AS 'y', CONCAT( YEAR(log_time), '/' , MONTHNAME(log_time)) AS 'x' FROM acc_log WHERE log_action LIKE 'Closed custom-n' AND YEAR(log_time) != 0 GROUP BY EXTRACT(YEAR_MONTH FROM log_time) ORDER BY YEAR(log_time), MONTH(log_time) ASC;",
                    'series' => "Custom not created requests by month"
                );

            global $availableRequestStates;
            foreach ($availableRequestStates as $state)
            {
                $queries[] = array(
                    'query' => "SELECT COUNT(DISTINCT log_id) AS 'y', CONCAT( YEAR(log_time), '/' , MONTHNAME(log_time)) AS 'x' FROM acc_log WHERE log_action LIKE 'Deferred to ".$state['defertolog']."' AND YEAR(log_time) != 0 GROUP BY EXTRACT(YEAR_MONTH FROM log_time) ORDER BY YEAR(log_time), MONTH(log_time) ASC;",
                    'series' => "Requests deferred to ".$state['deferto']." by month"
                );
            }

            global $baseurl;
            foreach ($this->createClosuresGraph($queries) as $i) {

                $out.= '<img src="'.$baseurl.'/render/' . $i[0] . '" alt="'.$i[1].'"/>';
            }

        }
        else
        {
            $out.= BootstrapSkin::displayAlertBox("Graph drawing is currently disabled.","alert-info","",false,false,true);
        }

        return $out;
    }

    public function getPageName()
    {
        return "MonthlyStats";
    }

    public function getPageTitle()
    {
        return "Monthly Statistics";
    }

    public function isProtected()
    {
        return true;
    }

    public function requiresWikiDatabase()
    {
        return false;
    }

    private function createClosuresGraph($queries)
    {
        $qb = new QueryBrowser();

        $imagehashes = array();

        foreach ($queries as $q) {
            $DataSet = new pData();
            $qResult = $qb->executeQueryToArray($q['query']);

            if(sizeof($qResult) > 0)
            {

                foreach($qResult as $row)
                {
                    $DataSet->AddPoint($row['y'], $q['series'], $row['x']);
                }

                $DataSet->AddAllSeries();
                $DataSet->SetAbsciseLabelSerie();

                $chartname = $this->createPathFromHash(md5(serialize($DataSet)));

                $imagehashes[] = array($chartname, $q['series']);

                if(!file_exists($chartname))
                {
                    $Test = new pChart(700,280);
                    $Test->setFontProperties("graph/Fonts/tahoma.ttf",8);
                    $Test->setGraphArea(50,30,680,200);
                    $Test->drawFilledRoundedRectangle(7,7,693,273,5,240,240,240);
                    $Test->drawRoundedRectangle(5,5,695,275,5,230,230,230);
                    $Test->drawGraphArea(255,255,255,TRUE);
                    $Test->drawScale($DataSet->GetData(),$DataSet->GetDataDescription(),SCALE_NORMAL,150,150,150,TRUE,45,2);
                    $Test->drawGrid(4,TRUE,230,230,230,50);

                    // Draw the 0 line
                    $Test->setFontProperties("graph/Fonts/tahoma.ttf",6);
                    $Test->drawTreshold(0,143,55,72,TRUE,TRUE);

                    // Draw the cubic curve graph
                    $Test->drawFilledCubicCurve($DataSet->GetData(),$DataSet->GetDataDescription(),.1,50);

                    // Finish the graph
                    $Test->setFontProperties("graph/Fonts/tahoma.ttf",10);
                    $Test->drawTitle(50,22, $q['series'],50,50,50,585);
                    $Test->Render("render/" . $chartname);
                }
            }
        }

        return $imagehashes;

    }

    /**
     * @param string $imghash
     */
    private function createPathFromHash($imghash, $basedirectory = "render/") {
        $imghashparts = str_split($imghash);
        $imgpath = array_shift($imghashparts) . "/" ;
        $imgpath .= array_shift($imghashparts) . "/" ;
        $imgpath .= array_shift($imghashparts) . "/" ;

        is_dir($basedirectory . $imgpath) || mkdir($basedirectory . $imgpath, 0777, true);

        $imgpath .= implode("", $imghashparts) ;
        return $imgpath;
    }
}

<?php

check_access('royalty',1);

$page=get_GET('p');

switch ($page)
{
	case 'sales':
        //select box
        $platform_array = $dmi_db->get_list('royalty_platform', 'name', 'platform_id', 'platform_id');

        $currency_sql  = ' SELECT a.currency_id ';
        $currency_sql .= '       ,CONCAT(CASE WHEN a.platform_id = 0 THEN \'All\' ELSE b.name END, \' - \', a.currency) AS platform ';
        $currency_sql .= '   FROM royalty_currency AS a ';
        $currency_sql .= '   LEFT JOIN royalty_platform AS b ';
        $currency_sql .= '     ON a.platform_id = b.platform_id ';
        $currency_sql .= '  ORDER BY a.sort_order ';
		$currency_array = $dmi_db->get_list3($currency_sql, 'currency_id', 'platform');

        //import
		if ($_POST)
	    {
            $message = '';
            $check_sql = '';
            $sql = '';
            $result = false;

            //keep
            $year = $_POST['Date_Year'];
            $month = $_POST['Date_Month'];
            $platform = $_POST['platform'];
            $currency = $_POST['currency'];

            //check history
            $check_sql1  = ' SELECT sales_id ';
            $check_sql1 .= '   FROM royalty_sales ';
            $check_sql1 .= '  WHERE year_and_month = ' . $year . $month;
            $check_sql1 .= '    AND platform_id = ' . $platform;
            $check_sql1 .= '    AND currency_id = ' . $currency;
			$check_data1 = $dmi_db->fetch_all2($check_sql1);

            //check currency
            $check_sql2  = ' SELECT platform_id ';
            $check_sql2 .= '   FROM royalty_currency ';
            $check_sql2 .= '  WHERE currency_id = ' . $currency;
            $check_data2 = $dmi_db->fetch_all2($check_sql2);

            if (count($check_data1) > 0)
            {
                $message = 'The data has already been imported.';
            }
            else if ($check_data2[0]['platform_id'] > 0 && $check_data2[0]['platform_id'] != $platform)
            {
                $message = 'Please select appropriate platform in Currency.';
            }
            else
            {
                require_once 'excel_reader2.php';

                $file_path = $_FILES['import_file']['tmp_name'];

                $file_name = $_FILES['import_file']['name'];
                $extension = pathinfo($file_name, PATHINFO_EXTENSION);

                if ($extension == 'xls')
                {
                	
                    
                    //currency rate
                    $rate_sql  = ' SELECT rate ';
                    $rate_sql .= '   FROM royalty_currency ';
                    $rate_sql .= '  WHERE currency_id = \'' . $currency . '\'';
                    $currency_rate = $dmi_db->fetch_all2($rate_sql);

                    $xls = new Spreadsheet_Excel_Reader();
                    $xls->setUTFEncoder('mb'); //configure multi byte
                    $xls->setOutputEncoding('UTF-8');
                    $xls->read($file_path, false);
					
                	/* import history
                	 * add ken 3-27-2013
                	 * */
                	$sqlh  = ' INSERT INTO royalty_sales_history (year_and_month, platform_id, currency_id, import_date) '
                           . ' VALUES ( '.$year . $month . ',' . $platform . ',' . $currency . ',now()) ';           	
                	$dmi_db->query($sqlh);
                	
                    //$maxRow = $xls->sheets[0]['numRows'];
                    $maxCol = $xls->sheets[0]['numCols'];

                    $index = 0;
                    $importCount = 0;
                    foreach ($xls->sheets[0]['cells'] as $raw)
                    {
                        if ($index > 0) // skip first title row
                        {
                            $data = array();
                            $blankFlag = '';

                            for ($i = 1; $i <= $maxCol; $i++)
                            {
                                // not insert when id is blank
                                if ($raw[1] != '')
                                {
                                    $data[] = $raw[$i];
                                }
                                else
                                {
                                    $blankFlag = '1';
                                }
                            }

                            if ($blankFlag == '')
                            {
                                $sql  = ' INSERT INTO royalty_sales (year_and_month, platform_id, relation_id, profit_rate, quantity, profit, currency_id, currency_rate, import_date) ';
                                $sql .= ' VALUES ( ';
                                $sql .= $year . $month . ',';
                                $sql .= '\'' . $platform . '\',';
                                foreach($data as $value)
                                {
                                    $sql .= '\'' . mysql_safe($value) . '\',';
                                }
                                $sql .= '\'' . $currency . '\',';
                                $sql .= $currency_rate[0]['rate'] . ',';
                                $sql .= '\'' . date('Y/m/d H:i:s', time()) . '\'';
                                $sql .= ' ) ';

                                //echo $sql;
                  			    $result = $dmi_db->query($sql);

                                $importCount ++;
                            }
                        }

                        $index ++;
                    }

                    if ($result === false)
                    {
                        $message = 'Error occured. Please contact IT department.';
                    }
                    else
                    {
                        $message = 'File was imported successfully (' . $importCount . ' records).';
                    }
                }
                else
                {
                    $message = 'Only Excel file (.xls) is available to import.';
                }
            }

            //assign
            $smarty->assign('time', $year . '-' . $month . '-01');
            $smarty->assign('platform', $platform);
            $smarty->assign('currency', $currency);
        }

        //history
        $platform_sql  = ' SELECT CONCAT(a.name, \' - \', b.currency) AS name ';
        $platform_sql .= '       ,a.platform_id ';
        $platform_sql .= '       ,b.currency_id ';
        $platform_sql .= '       ,b.sort_order ';
        $platform_sql .= '   FROM royalty_platform AS a ';
        $platform_sql .= '   LEFT JOIN royalty_currency AS b ';
        $platform_sql .= '     ON a.platform_id = b.platform_id ';
        $platform_sql .= '  WHERE b.platform_id > 0 ';
        $platform_sql .= ' UNION ALL ';
        $platform_sql .= ' SELECT CONCAT(name, \' - USD\') AS name ';
        $platform_sql .= '       ,platform_id ';
        $platform_sql .= '       ,1 AS currency_id ';
        $platform_sql .= '       ,1 sort_order ';
        $platform_sql .= '   FROM royalty_platform ';
        $platform_sql .= '  ORDER BY platform_id, sort_order ';

		$platform_data = $dmi_db->fetch_all2($platform_sql);
		/*var_dump($platform_sql);*/

        $sales_sql  = ' SELECT DISTINCT ';
        $sales_sql .= '        year_and_month ';
        $sales_sql .= '       ,platform_id ';
        $sales_sql .= '       ,currency_id ';
        $sales_sql .= '   FROM royalty_sales ';
        $sales_sql .= '  ORDER BY year_and_month DESC ';

		$sales_data = $dmi_db->fetch_all2($sales_sql);

        $index = 0;
        for ($i = 0; $i < count($sales_data); $i++)
        {
            if ($sales_data[$i]['year_and_month'] <> $year_and_month)
            {
                $index ++;
            }

            $history[$index][0] = $sales_data[$i]['year_and_month'];

            for ($j = 0; $j < count($platform_data); $j++)
            {
                /* check
                if ($i == 2)
                {
                    echo  "year_month[$i] = " . $sales_data[$i]['year_and_month'] . 
                          "<br />sales_platform_id[$i] = " . $sales_data[$i]['platform_id'] . 
                          "<br />sales_currency_id[$i] = " . $sales_data[$i]['currency_id'] . 
                          "<br />plat_platform_id[$j] = " . $platform_data[$j]['platform_id'] . 
                          "<br />plat_currency_id[$j] = " . $platform_data[$j]['currency_id'] . '<br /><br />';
                }
                */
                if ($sales_data[$i]['platform_id'] == $platform_data[$j]['platform_id'] && $sales_data[$i]['currency_id'] == $platform_data[$j]['currency_id'])
                {
                    $history[$index][$j + 1] = '*';
                }
            }

            $year_and_month = $sales_data[$i]['year_and_month'];
        }

        /*
        $history_sql  = ' SELECT x.year_and_month ';
        $history_sql .= '       ,GROUP_CONCAT(x.name SEPARATOR \', \') AS name ';
        $history_sql .= '   FROM ';
        $history_sql .= '        (SELECT DISTINCT ';
        $history_sql .= '                a.year_and_month ';
        $history_sql .= '               ,CONCAT(b.name, \' - \', c.currency) AS name ';
        $history_sql .= '           FROM royalty_sales AS a ';
        $history_sql .= '           LEFT JOIN royalty_platform AS b ';
        $history_sql .= '             ON a.platform_id = b.platform_id ';
        $history_sql .= '           LEFT JOIN royalty_currency AS c ';
        $history_sql .= '             ON a.currency_id = c.currency_id ';
        $history_sql .= '          ORDER BY a.year_and_month DESC, a.currency_id ';
        $history_sql .= '        ) AS x ';
        $history_sql .= '  GROUP BY x.year_and_month ';
        $history_sql .= '  ORDER BY x.year_and_month DESC ';

		$history_data = $dmi_db->fetch_all2($history_sql);
        */
		
        /* import history 
         * add ken 3-28-2013
         * */
        
        $month_sql  = ' SELECT DISTINCT year_and_month FROM royalty_sales_history ';
        $month_sql .= '  ORDER BY year_and_month DESC ';

		$month_data = $dmi_db->fetch_all2($month_sql);
        $import_history = array();
        foreach ($month_data as $m) {
        	$month = $m['year_and_month'];
        	$history_sql = "SELECT DISTINCT c.year_and_month, d.name 
        				FROM royalty_sales_history c RIGHT JOIN 
						( SELECT CONCAT( a.name, ' - ', b.currency ) AS name, a.platform_id, b.currency_id, b.sort_order
						  FROM royalty_platform AS a INNER JOIN royalty_currency AS b ON a.platform_id = b.platform_id
						  WHERE b.platform_id >0 
						  UNION ALL 
						  SELECT CONCAT( name, ' - USD' ) AS name, platform_id, 1 AS currency_id, 1 sort_order FROM royalty_platform
						) AS d ON d.platform_id = c.platform_id AND d.currency_id = c.currency_id
						AND c.year_and_month = $month
						ORDER BY d.platform_id, d.sort_order";
            $import_history_data = $dmi_db->fetch_all2($history_sql);
            $import_history[$month]= $import_history_data;
        }   

        
        //assign
        $smarty->assign('platform_array', $platform_array);
        $smarty->assign('currency_array', $currency_array);
        $smarty->assign('platform_data', $platform_data);
        $smarty->assign('history', $history);
        $smarty->assign('count_col', count($platform_data) + 1);
        $smarty->assign('history_data', $history_data);
        $smarty->assign('message', $message);
        $smarty->assign('import_history', $import_history);
        
        /* mod 3-18-2013 */
		$smarty->assign('readonly', !check_access( 'royalty', 2, false ));
        
		$tpl = 'royalty_sales.tpl';

		break;


	case 'publisher':
        //select box
        $add_row = array('' => 'All');
        $publisher_array = $dmi_db->get_list2('publishers', 'publisher_name_english', 'publisher_name_english', 'publisher_id', $add_row);

        //export
		if ($_POST)
		{
            $message = '';
            $sql = '';

            //keep
            $from_year = $_POST['from_Year'];
            $from_month = $_POST['from_Month'];
            $to_year = $_POST['to_Year'];
            $to_month = $_POST['to_Month'];
            $publisher = $_POST['publisher'];
            $fuji = $_POST['fuji'];
            $summarize = $_POST['summarize'];

            //extract data
            $platform = $dmi_db->fetch_all('royalty_platform');

            for ($i = 0; $i < count($platform); $i++)
            {
                if ($i > 0)
                {
                    $sql .= ' UNION ALL ';
                }

                $sql .= '(SELECT e.publisher_name_english ';
                $sql .= '       ,b.book_id ';
                $sql .= '       ,a.relation_id ';
                $sql .= '       ,f.label_name ';
                $sql .= '       ,i.author_name ';
                $sql .= '       ,CASE WHEN b.' . $platform[$i]['relation_column2'] . ' <> \'\' AND a.relation_id = b.' . $platform[$i]['relation_column'];
                $sql .= '             THEN CONCAT(c.property_title, \' Part1\') ';
                $sql .= '             WHEN b.' . $platform[$i]['relation_column2'] . ' <> \'\' AND a.relation_id = b.' . $platform[$i]['relation_column2'];
                $sql .= '             THEN CONCAT(c.property_title, \' Part2\') ';
                $sql .= '             ELSE c.property_title ';
                $sql .= '         END AS property_title ';
                $sql .= '       ,CASE WHEN b.' . $platform[$i]['relation_column2'] . ' <> \'\' AND a.relation_id = b.' . $platform[$i]['relation_column'];
                $sql .= '             THEN CONCAT(b.publish_title, \' Part1\') ';
                $sql .= '             WHEN b.' . $platform[$i]['relation_column2'] . ' <> \'\' AND a.relation_id = b.' . $platform[$i]['relation_column2'];
                $sql .= '             THEN CONCAT(b.publish_title, \' Part2\') ';
                $sql .= '             ELSE b.publish_title ';
                $sql .= '         END AS publish_title ';
                $sql .= '       ,d.name ';
                /*mod ken 9-19-2013 -->*/
                if ($summarize != '1' || ($summarize =='1' &&  $platform[$i]['platform_id'] == 2) ) //summarize 70 and 35 seperately for kindle.
                {
	                $sql .= '       ,CASE WHEN a.profit_rate = 0 ';
	                $sql .= '             THEN \'\' ' ;
	                $sql .= '             ELSE a.profit_rate ' ;
	                $sql .= '         END AS profit_rate ' ;
                }
                else 
                {
                	$sql .= '       ,\'\' AS profit_rate ' ;
                }
                $sql .= $summarize != '1' ? '       ,g.currency ' : '       ,\'\' as currency ';
                /*mod ken 9-19-2013 <--*/
                $sql .= '       ,SUM(a.quantity) AS total_quantity ';
                $sql .= '       ,CASE WHEN a.relation_id = b.' . $platform[$i]['relation_column'];
                $sql .= '             THEN b.' . $platform[$i]['srp_column'];
                $sql .= '             WHEN a.relation_id = b.' . $platform[$i]['relation_column2'];
                $sql .= '             THEN b.' . $platform[$i]['srp_column2'];
                $sql .= '         END AS srp ';
                $sql .= '       ,CASE WHEN a.relation_id = b.' . $platform[$i]['relation_column'];
                $sql .= '             THEN SUM(a.quantity) * b.' . $platform[$i]['srp_column'];
                $sql .= '             WHEN a.relation_id = b.' . $platform[$i]['relation_column2'];
                $sql .= '             THEN SUM(a.quantity) * b.' . $platform[$i]['srp_column2'];
                $sql .= '         END AS total_price ';

                /* mod ken 8-20-2013 total profit start
                 *  SUM(a.profit) * a.currency_rate  ==>  SUM(a.profit * a.currency_rate)
                 *  */
                
                if ($platform[$i]['platform_id'] == 10 || $platform[$i]['platform_id'] == 1) // 70% profit when Wowio or eManga /*mod ken 9-17-2013*/
                {
                    $sql .= '       ,CASE WHEN a.relation_id = b.' . $platform[$i]['relation_column'];
                    $sql .= '             THEN SUM(a.quantity) * b.' . $platform[$i]['srp_column'] . ' - (SUM(a.profit * a.currency_rate) * 0.7)';
                    $sql .= '             WHEN a.relation_id = b.' . $platform[$i]['relation_column2'];
                    $sql .= '             THEN SUM(a.quantity) * b.' . $platform[$i]['srp_column2'] . ' - (SUM(a.profit * a.currency_rate) * 0.7)';
                    $sql .= '         END AS total_fee ';
                    $sql .= '       ,SUM(a.profit * a.currency_rate) * 0.7 AS total_profit ';
                    if ($fuji != '') //rate 4% when scanned by fuji film
                    {
                        $sql .= '   ,4 AS royalty_rate ';
                        $sql .= '   ,SUM(a.profit * a.currency_rate) * 0.7 * (4 / 100) AS royalty_due ';
                    }
                    else
                    {
                        $sql .= '   ,b.royalty_rate ';
                        $sql .= '   ,SUM(a.profit * a.currency_rate) * 0.7  * (b.royalty_rate / 100) AS royalty_due ';
                    }
                }
                else if ($platform[$i]['platform_id'] == 6 || $platform[$i]['platform_id'] == 8) // Half profit when Apple or Google Android
                {
                    $sql .= '       ,CASE WHEN a.relation_id = b.' . $platform[$i]['relation_column'];
                    $sql .= '             THEN SUM(a.quantity) * b.' . $platform[$i]['srp_column'] . ' - (SUM(a.profit * a.currency_rate) / 2)';
                    $sql .= '             WHEN a.relation_id = b.' . $platform[$i]['relation_column2'];
                    $sql .= '             THEN SUM(a.quantity) * b.' . $platform[$i]['srp_column2'] . ' - (SUM(a.profit * a.currency_rate) / 2)';
                    $sql .= '         END AS total_fee ';
                    $sql .= '       ,SUM(a.profit * a.currency_rate) / 2 AS total_profit ';
                    if ($fuji != '') //rate 4% when scanned by fuji film
                    {
                        $sql .= '   ,4 AS royalty_rate ';
                        $sql .= '   ,SUM(a.profit * a.currency_rate) / 2 * (4 / 100) AS royalty_due ';
                    }
                    else
                    {
                        $sql .= '   ,b.royalty_rate ';
                        $sql .= '   ,SUM(a.profit * a.currency_rate) / 2 * (b.royalty_rate / 100) AS royalty_due ';
                    }
                }
                else
                {
                    $sql .= '       ,CASE WHEN a.relation_id = b.' . $platform[$i]['relation_column'];
                    $sql .= '             THEN SUM(a.quantity) * b.' . $platform[$i]['srp_column'] . ' - (SUM(a.profit * a.currency_rate) )';
                    $sql .= '             WHEN a.relation_id = b.' . $platform[$i]['relation_column2'];
                    $sql .= '             THEN SUM(a.quantity) * b.' . $platform[$i]['srp_column2'] . ' - (SUM(a.profit * a.currency_rate) )';
                    $sql .= '         END AS total_fee ';
                    $sql .= '       ,SUM(a.profit * a.currency_rate) AS total_profit ';

                    if ($fuji != '') //rate 4% when scanned by fuji film
                    {
                        $sql .= '   ,4 AS royalty_rate ';
                        $sql .= '   ,SUM(a.profit * a.currency_rate)  * (4 / 100) AS royalty_due ';
                    }
                    else
                    {
                        $sql .= '   ,b.royalty_rate ';
                        $sql .= '   ,SUM(a.profit * a.currency_rate)  * (b.royalty_rate / 100) AS royalty_due ';
                    }
                }
			   /* mod ken 8-20-2013 total profit end */
                $sql .= '   ,d.sort_order '; //add ken 9-23-2013
                $sql .= '   FROM royalty_sales AS a ';
                $sql .= '   LEFT JOIN properties_dmi AS b ';
                $sql .= '     ON a.relation_id = b.' . $platform[$i]['relation_column'];
                $sql .= '     OR a.relation_id = b.' . $platform[$i]['relation_column2'];
                $sql .= '   LEFT JOIN properties AS c ';
                $sql .= '     ON b.property_id = c.property_id ';
                $sql .= '   LEFT JOIN royalty_platform AS d ';
                $sql .= '     ON a.platform_id = d.platform_id ';
                $sql .= '   LEFT JOIN publishers AS e ';
                $sql .= '     ON c.publisher_id = e.publisher_id ';
                $sql .= '   LEFT JOIN labels AS f ';
                $sql .= '     ON b.label_id = f.label_id ';
                $sql .= '   LEFT JOIN royalty_currency AS g ';
                $sql .= '     ON a.currency_id = g.currency_id ';
                $sql .= '   LEFT JOIN properties_authors AS h ';
                $sql .= '     ON b.property_id = h.property_id ';
                $sql .= '   LEFT JOIN authors AS i ';
                $sql .= '     ON h.author_id = i.author_id ';

                $sql .= '  WHERE a.year_and_month >= ' . $from_year . $from_month;
                $sql .= '    AND a.year_and_month <= ' . $to_year . $to_month;
                $sql .= '    AND a.platform_id = ' . $platform[$i]['platform_id'];
                $sql .= '    AND h.credited_as = \'Author\' ';
                if ($publisher != '')
                {
                    $sql .= '    AND c.publisher_id = ' . $publisher;
                }
                if ($fuji != '')
                {
                    $sql .= '    AND b.scanned_by_fuji  = \'1\' ';
                }

                $sql .= '  GROUP BY e.publisher_name_english ';
                $sql .= '          ,b.book_id ';
                $sql .= '          ,a.relation_id ';
                $sql .= '          ,f.label_name ';
                $sql .= '          ,c.property_title ';
                $sql .= '          ,b.publish_title ';
                $sql .= '          ,d.name ';

                /*mod ken 9-19-2013 -->*/
                if ($summarize != '1' || ($summarize =='1' &&  $platform[$i]['platform_id'] == 2) ) $sql .= '          ,profit_rate '; //summarize 70 and 35 seperately for kindle.
                if ($summarize != '1') $sql .= '          ,g.currency ';
                $sql .= ' ) ';
                /*mod ken 9-19-2013 <--*/
            }
            $sql .= ' ORDER BY publisher_name_english, label_name, publish_title, sort_order, name, profit_rate '; /* mod ken 9-23-2013 */
            //echo $sql . '<br />'; exit();
           
			$data = $dmi_db->fetch_all2($sql);
			
			/*add ken 9-20-2013 combine nook b&w and nook color -->*/
			if ($summarize == '1')
			{ 
				$bookid_old = '';
				$platform_old = '';
				$data_new = array();
				$nook = array(); 
				foreach ($data as $d)
				{
					if( ( (($platform_old !== 'Nook Black and White' && $platform_old !== 'Nook Color')) || $d['publish_title'] !== $bookid_old ) 
					    && ($d['name'] === 'Nook Black and White' || $d['name']=== 'Nook Color') )
					{
						if(count($nook)>0)	$data_new[]=$nook;
						$nook = $d;
						$nook['relation_id'] = '';		 
						$nook['name'] = 'Nook';							
					}
					elseif($d['publish_title'] === $bookid_old && ($d['name'] === 'Nook Black and White' || $d['name']=== 'Nook Color') )
					{						
							$nook['total_quantity'] += $d['total_quantity'];
							$nook['total_price'] += $d['total_price'];
							$nook['total_fee'] += $d['total_fee'];
							$nook['total_profit'] += $d['total_profit'];
							$nook['royalty_due'] += $d['royalty_due'];
					}
					else 
					{
					   	if(count($nook)>0)	$data_new[]=$nook;
					   	$nook = array();
						$data_new[] = $d;	
					}
					$bookid_old = $d['publish_title'];
					$platform_old = $d['name'];
				}
				
				if(count($nook)>0)	$data_new[]=$nook; //last record. add ken 11-04-2013
				
				$data = $data_new;
			}
			/*add ken 9-20-2013 <--*/
			
            //write to excel
            if (count($data) == 0)
            {
                $message = 'There is no data.';
            }
            else
            {
        		//ini_set( 'memory_limit', '64M' );
        		require_once('/home/dmi/public_html/libs/PHPExcel/PHPExcel.php');
        		require_once('/home/dmi/public_html/libs/PHPExcel/PHPExcel/Writer/Excel5.php');
                $file_name = "RoyaltyPublisherReport.xls";

        		$objPHPExcel = new PHPExcel();
        		$objWriter = new PHPExcel_Writer_Excel5($objPHPExcel);
        		$objWorksheet = $objPHPExcel->getActiveSheet();

        		if($objWriter)
        		{
        			$objWorksheet->setCellValue( 'A1', 'Term' );
        			$objWorksheet->setCellValue( 'B1', 'Publisher' );
        			$objWorksheet->setCellValue( 'C1', 'Author' );
        			$objWorksheet->setCellValue( 'D1', 'Our Book ID' );
        			$objWorksheet->setCellValue( 'E1', 'Vendor ID' );
        			$objWorksheet->setCellValue( 'F1', 'Imprint' );
        			$objWorksheet->setCellValue( 'G1', 'Japanese Title' );
        			$objWorksheet->setCellValue( 'H1', 'English Title' );
        			$objWorksheet->setCellValue( 'I1', 'Platform' );
        			$objWorksheet->setCellValue( 'J1', 'Profit Rate' );
        			$objWorksheet->setCellValue( 'K1', 'Currency' );
        			$objWorksheet->setCellValue( 'L1', 'Quantity' );
        			$objWorksheet->setCellValue( 'M1', 'SRP' );
        			$objWorksheet->setCellValue( 'N1', 'Total Sales' );
        			$objWorksheet->setCellValue( 'O1', 'Fee' );
        			$objWorksheet->setCellValue( 'P1', 'Profit' );
        			$objWorksheet->setCellValue( 'Q1', 'Rate' );
        			$objWorksheet->setCellValue( 'R1', 'Royalty' );

        			$rowCount = 2;

        			foreach($data as $col)
        			{
        				        				
        				$objWorksheet->setCellValueByColumnAndRow( 0, $rowCount, $from_year . $from_month . ' - ' . $to_year . $to_month);
        				$objWorksheet->setCellValueByColumnAndRow( 1, $rowCount, $col['publisher_name_english'] );
        				$objWorksheet->setCellValueByColumnAndRow( 2, $rowCount, $col['author_name'] );
        				$objWorksheet->setCellValueByColumnAndRow( 3, $rowCount, $col['book_id'] );
        				$objWorksheet->setCellValueExplicitByColumnAndRow( 4, $rowCount, $col['relation_id'], PHPExcel_Cell_DataType::TYPE_STRING );
        				$objWorksheet->setCellValueByColumnAndRow( 5, $rowCount, $col['label_name'] );
        				$objWorksheet->setCellValueByColumnAndRow( 6, $rowCount, $col['property_title'] );
        				$objWorksheet->setCellValueByColumnAndRow( 7, $rowCount, $col['publish_title'] );
        				$objWorksheet->setCellValueByColumnAndRow( 8, $rowCount, $col['name'] );
        				$objWorksheet->setCellValueByColumnAndRow( 9, $rowCount, $col['profit_rate'] );
        				$objWorksheet->setCellValueByColumnAndRow( 10, $rowCount, $col['currency'] );
        				$objWorksheet->setCellValueByColumnAndRow( 11, $rowCount, $col['total_quantity'] );
        				
        				//mod ken 8-12-2013
        			  /*$objWorksheet->setCellValueByColumnAndRow( 11, $rowCount, floor($col['srp'] * 100) / 100 );
        				$objWorksheet->setCellValueByColumnAndRow( 12, $rowCount, floor($col['total_price'] * 100) / 100 );
        				$objWorksheet->setCellValueByColumnAndRow( 13, $rowCount, floor($col['total_fee'] * 100) / 100 );
        				$objWorksheet->setCellValueByColumnAndRow( 14, $rowCount, floor($col['total_profit'] * 100) / 100 );
        				$objWorksheet->setCellValueByColumnAndRow( 15, $rowCount, floor($col['royalty_rate'] * 100) / 100 );
        				$objWorksheet->setCellValueByColumnAndRow( 16, $rowCount, floor($col['royalty_due'] * 100) / 100 );*/      				
        				$objWorksheet->setCellValueByColumnAndRow( 12, $rowCount, floor(bcmul($col['srp'], 100, 2)) / 100 );
        				$objWorksheet->setCellValueByColumnAndRow( 13, $rowCount, floor(bcmul($col['total_price'], 100, 2)) / 100 );
        				$objWorksheet->setCellValueByColumnAndRow( 14, $rowCount, floor(bcmul($col['total_fee'], 100, 2)) / 100 );
        				$objWorksheet->setCellValueByColumnAndRow( 15, $rowCount, floor(bcmul($col['total_profit'], 100, 2)) / 100 );
        				$objWorksheet->setCellValueByColumnAndRow( 16, $rowCount, floor(bcmul($col['royalty_rate'], 100, 2)) / 100 );
        				$objWorksheet->setCellValueByColumnAndRow( 17, $rowCount, floor(bcmul($col['royalty_due'], 100, 2)) / 100 );

        				$rowCount++;
        			}

        			//for($i = 0; $i < 14; $i++)
        			//{
        			//	$objWorksheet->getColumnDimensionByColumn($i)->setAutoSize(true);
        			//}

        			$objWriter->save($file_name);
        			header('Content-type: application/vnd.ms-excel');
        			header('Content-Disposition: attachment; filename="'.$file_name.'"');
        			readfile($file_name);
        			exit();
                }
            }

            //assign
            $smarty->assign('from_time', $from_year . '-' . $from_month . '-01');
            $smarty->assign('to_time', $to_year . '-' . $to_month . '-01');
            $smarty->assign('publisher', $publisher);
        }

        //assign
        $smarty->assign('publisher_array', $publisher_array);
        $smarty->assign('message', $message);
		$tpl = 'royalty_publisher.tpl';

		break;


	case 'localizer':
        //export
		if ($_POST)
		{
            $message = '';
            $sql = '';

            //keep
            $from_year = $_POST['from_Year'];
            $from_month = $_POST['from_Month'];
            $to_year = $_POST['to_Year'];
            $to_month = $_POST['to_Month'];

            //extract data
            $platform = $dmi_db->fetch_all('royalty_platform');
            $relation = array('translator_1_reg', 'translator_2_reg' ,'translator_3_reg', 'editor_1_reg', 'editor_2_reg', 'editor_3_reg', 'lettering_1_reg', 'lettering_2_reg', 'lettering_3_reg');
            $rate = array('translator_1_percentage', 'translator_2_percentage' ,'translator_3_percentage', 'editor_1_percentage', 'editor_2_percentage', 'editor_3_percentage', 'lettering_1_percentage', 'lettering_2_percentage', 'lettering_3_percentage');
            $position = array('Translator', 'Translator', 'Translator', 'Editor', 'Editor', 'Editor', 'Letterer', 'Letterer', 'Letterer');

            for ($i = 0; $i < count($platform); $i++)
            {
                if ($i > 0)
                {
                    $sql .= ' UNION ALL ';
                }

                for ($j = 0; $j < count($relation); $j++)
                {
                    if ($j > 0)
                    {
                        $sql .= ' UNION ALL ';
                    }

                    $sql .= '(SELECT e.reg_id ';
                    $sql .= '       ,e.first_name ';
                    $sql .= '       ,e.last_name ';
                    $sql .= '       ,e.pen_name  ';
                    $sql .= '       ,e.email ';
                    $sql .= '       ,e.address1 ';
                    $sql .= '       ,e.address2 ';
                    $sql .= '       ,e.city ';
                    $sql .= '       ,e.state ';
                    $sql .= '       ,e.country ';
                    $sql .= '       ,e.zip ';
                    $sql .= '       ,b.book_id ';
                    $sql .= '       ,a.relation_id ';
                    $sql .= '       ,f.name ';
                    $sql .= '       ,CASE WHEN b.' . $platform[$i]['relation_column2'] . ' <> \'\' AND a.relation_id = b.' . $platform[$i]['relation_column'];
                    $sql .= '             THEN CONCAT(b.publish_title, \' Part1\') ';
                    $sql .= '             WHEN b.' . $platform[$i]['relation_column2'] . ' <> \'\' AND a.relation_id = b.' . $platform[$i]['relation_column2'];
                    $sql .= '             THEN CONCAT(b.publish_title, \' Part2\') ';
                    $sql .= '             ELSE b.publish_title ';
                    $sql .= '         END AS publish_title ';
                    $sql .= '       ,c.isbn_13 ';
                    $sql .= '       ,\'' . $position[$j] . '\' AS position ';
                    $sql .= '       ,g.currency ';
                    $sql .= '       ,SUM(a.quantity) AS total_quantity ';
                    $sql .= '       ,' . $rate[$j] . ' AS rate ';
                    if ($platform[$i]['platform_id'] == 1 || $platform[$i]['platform_id'] == 10) // 70% profit when eManga or Wowio
                    {
                        $sql .= '       ,SUM(a.profit) * 0.7 * a.currency_rate AS total_profit ';
                        $sql .= '       ,SUM(a.profit) * 0.7 * a.currency_rate * (' . $rate[$j] . ' / 100) AS royalty';
                    }
                    else if ($platform[$i]['platform_id'] == 6 || $platform[$i]['platform_id'] == 8) // Half profit when Apple or Google Android
                    {
                        $sql .= '       ,SUM(a.profit) * a.currency_rate / 2 AS total_profit ';
                        $sql .= '       ,SUM(a.profit) * a.currency_rate / 2 * (' . $rate[$j] . ' / 100) AS royalty';
                    }
                    else
                    {
                        $sql .= '       ,SUM(a.profit) * a.currency_rate AS total_profit ';
                        $sql .= '       ,SUM(a.profit) * a.currency_rate * (' . $rate[$j] . ' / 100) AS royalty';
                    }

                    $sql .= '   FROM royalty_sales AS a ';
                    $sql .= '   LEFT JOIN properties_dmi AS b ';
                    $sql .= '     ON a.relation_id = b.' . $platform[$i]['relation_column'];
                    $sql .= '     OR a.relation_id = b.' . $platform[$i]['relation_column2'];
                    $sql .= '   LEFT JOIN isbn_track AS c ';
                    $sql .= '     ON b.e_isbn = c.isbn_id ';
                    $sql .= '   LEFT JOIN properties_dmi_localizer AS d ';
                    $sql .= '     ON b.book_id = d.book_id ';
                    $sql .= '  INNER JOIN localizer AS e ';
                    $sql .= '     ON d.' . $relation[$j] . ' = e.reg_id ';
                    $sql .= '   LEFT JOIN royalty_platform AS f ';
                    $sql .= '     ON a.platform_id = f.platform_id ';
                    $sql .= '   LEFT JOIN royalty_currency AS g ';
                    $sql .= '     ON a.currency_id = g.currency_id ';

                    $sql .= '  WHERE a.year_and_month >= ' . $from_year . $from_month;
                    $sql .= '    AND a.year_and_month <= ' . $to_year . $to_month;
                    $sql .= '    AND a.platform_id = ' . $platform[$i]['platform_id'];

                    $sql .= '  GROUP BY e.reg_id ';
                    $sql .= '          ,e.first_name ';
                    $sql .= '          ,e.last_name ';
                    $sql .= '          ,e.pen_name ';
                    $sql .= '          ,e.email ';
                    $sql .= '          ,e.address1 ';
                    $sql .= '          ,e.address2 ';
                    $sql .= '          ,e.city ';
                    $sql .= '          ,e.state ';
                    $sql .= '          ,e.country ';
                    $sql .= '          ,e.zip ';
                    $sql .= '          ,b.book_id ';
                    $sql .= '          ,a.relation_id ';
                    $sql .= '          ,f.name ';
                    $sql .= '          ,publish_title ';
                    $sql .= '          ,c.isbn_13 ';
                    $sql .= '          ,position ';
                    $sql .= '          ,g.currency ';
                    $sql .= '          ,rate )';
                }
            }
            $sql .= ' ORDER BY pen_name, publish_title, position ';
            //echo $sql . '<br />';

			$data = $dmi_db->fetch_all2($sql);

            //write to excel
            if (count($data) == 0)
            {
                $message = 'There is no data.';
            }
            else
            {
        		ini_set( 'memory_limit', '512M' );
        		require_once('/home/dmi/public_html/libs/PHPExcel/PHPExcel.php');
        		require_once('/home/dmi/public_html/libs/PHPExcel/PHPExcel/Writer/Excel5.php');
                $file_name = "RoyaltyLocalizerReport.xls";

        		$objPHPExcel = new PHPExcel();
        		$objWriter = new PHPExcel_Writer_Excel5($objPHPExcel);
        		$objWorksheet = $objPHPExcel->getActiveSheet();

        		if($objWriter)
        		{
        			$objWorksheet->setCellValue( 'A1', 'Term' );
        			$objWorksheet->setCellValue( 'B1', 'ID#' );
        			$objWorksheet->setCellValue( 'C1', 'First Name' );
        			$objWorksheet->setCellValue( 'D1', 'Last Name' );
        			$objWorksheet->setCellValue( 'E1', 'Pen Name' );
        			$objWorksheet->setCellValue( 'F1', 'Email' );
        			$objWorksheet->setCellValue( 'G1', 'Address1' );
        			$objWorksheet->setCellValue( 'H1', 'Address2' );
        			$objWorksheet->setCellValue( 'I1', 'City' );
        			$objWorksheet->setCellValue( 'J1', 'State' );
        			$objWorksheet->setCellValue( 'K1', 'Country' );
        			$objWorksheet->setCellValue( 'L1', 'Zip' );
        			$objWorksheet->setCellValue( 'M1', 'Our Book ID' );
        			$objWorksheet->setCellValue( 'N1', 'Vendor ID' );
        			$objWorksheet->setCellValue( 'O1', 'Platform' );
        			$objWorksheet->setCellValue( 'P1', 'Title' );
        			$objWorksheet->setCellValue( 'Q1', 'eISBN' );
        			$objWorksheet->setCellValue( 'R1', 'Position' );
        			$objWorksheet->setCellValue( 'S1', 'Currency' );
        			$objWorksheet->setCellValue( 'T1', 'Quantity' );
        			$objWorksheet->setCellValue( 'U1', 'Profit' );
        			$objWorksheet->setCellValue( 'V1', 'Rate' );
        			$objWorksheet->setCellValue( 'W1', 'Royalty' );

        			$rowCount = 2;

        			foreach($data as $col)
        			{
        				        				
        				$objWorksheet->setCellValueByColumnAndRow( 0, $rowCount, $from_year . $from_month . ' - ' . $to_year . $to_month);
        				$objWorksheet->setCellValueByColumnAndRow( 1, $rowCount, $col['reg_id'] );
        				$objWorksheet->setCellValueByColumnAndRow( 2, $rowCount, $col['first_name'] );
        				$objWorksheet->setCellValueByColumnAndRow( 3, $rowCount, $col['last_name'] );
        				$objWorksheet->setCellValueByColumnAndRow( 4, $rowCount, $col['pen_name'] );
        				$objWorksheet->setCellValueByColumnAndRow( 5, $rowCount, $col['email'] );
        				$objWorksheet->setCellValueByColumnAndRow( 6, $rowCount, $col['address1'] );
        				$objWorksheet->setCellValueByColumnAndRow( 7, $rowCount, $col['address2'] );
        				$objWorksheet->setCellValueByColumnAndRow( 8, $rowCount, $col['city'] );
        				$objWorksheet->setCellValueByColumnAndRow( 9, $rowCount, $col['state'] );
        				$objWorksheet->setCellValueByColumnAndRow( 10, $rowCount, $col['country'] );
        				$objWorksheet->setCellValueByColumnAndRow( 11, $rowCount, $col['zip'] );
        				$objWorksheet->setCellValueByColumnAndRow( 12, $rowCount, $col['book_id'] );
        				$objWorksheet->setCellValueExplicitByColumnAndRow( 13, $rowCount, $col['relation_id'], PHPExcel_Cell_DataType::TYPE_STRING );
        				$objWorksheet->setCellValueByColumnAndRow( 14, $rowCount, $col['name'] );
        				$objWorksheet->setCellValueByColumnAndRow( 15, $rowCount, $col['publish_title'] );
        				$objWorksheet->setCellValueExplicitByColumnAndRow( 16, $rowCount, $col['isbn_13'], PHPExcel_Cell_DataType::TYPE_STRING );
        				$objWorksheet->setCellValueByColumnAndRow( 17, $rowCount, $col['position'] );
        				$objWorksheet->setCellValueByColumnAndRow( 18, $rowCount, $col['currency'] );
        				$objWorksheet->setCellValueByColumnAndRow( 19, $rowCount, floor($col['total_quantity'] * 100) / 100 );
        				$objWorksheet->setCellValueByColumnAndRow( 20, $rowCount, floor($col['total_profit'] * 100) / 100 );
        				$objWorksheet->setCellValueByColumnAndRow( 21, $rowCount, floor($col['rate'] * 100) / 100 );
        				$objWorksheet->setCellValueByColumnAndRow( 22, $rowCount, floor($col['royalty'] * 100) / 100 );

        				$rowCount++;
        			}

        			//for($i = 0; $i < 14; $i++)
        			//{
        			//	$objWorksheet->getColumnDimensionByColumn($i)->setAutoSize(true);
        			//}

        			$objWriter->save($file_name);
        			header('Content-type: application/vnd.ms-excel');
        			header('Content-Disposition: attachment; filename="'.$file_name.'"');
        			readfile($file_name);
        			exit();
                }
            }

            //assign
            $smarty->assign('from_time', $from_year . '-' . $from_month . '-01');
            $smarty->assign('to_time', $to_year . '-' . $to_month . '-01');
        }

        //assign
        $smarty->assign('message', $message);
		$tpl = 'royalty_localizer.tpl';

		break;
	
	case 'paid_localizer':   /* add ken 3-4-2013*/
		
				
		include('includes/classes/royalty_localizer.php');
		$mindate = royalty_localizer::get_startdate();
		$maxdate = new DateTime();
		
		$smarty->assign('min_year', intval($mindate->format('Y')));
		$smarty->assign('max_year', intval($maxdate->format('Y')));
		
		//localizer index
		$idx = get_GET('idx');
		$startdate = new DateTime(get_GET('startdate'));		
		$enddate = new DateTime(get_GET('enddate'));
		
		
		if ($_POST)
		{   $message = '';
            $sql = '';
			if ($_POST['period']=='all'){
				$startdate = $mindate;
				$enddate = royalty_localizer::get_period($maxdate,true);	
			} else{
				$startdate = royalty_localizer::get_period(new DateTime($_POST['start_year']
														   .'-'.$_POST['start_term'].'-01'));
				$enddate = royalty_localizer::get_period(new DateTime($_POST['end_year']
														   .'-'.$_POST['end_term'].'-01'),true);
			}
//var_dump($startdate->format('Y-m-d').'-'.$enddate->format('Y-m-d'));
            /* eg 2012-01-01-2012-07-01, 2012-07-01-2013-01-01 */
			$_SESSION['isEmpty'] = true;
			$_SESSION['periods'] = null;
			$_SESSION['paidloys'] = array();
			$idx = '0';
		}
		
		if ($idx != '')
		{
			   		
            $sql = "SELECT * FROM localizer order by reg_id LIMIT $idx,1";
            
			$locs = $dmi_db->fetch_all2($sql);
//var_dump($locs);
					
	     if ($locs)
	     {
			
			foreach ($locs as $loc){
				
				$royalty = new royalty_localizer($loc['reg_id']);
				
//var_dump($loc['reg_id']);
				
				$tmp_startdate = clone $startdate;
				$paidloy = array();
				$paidloy['reg_id'] = $loc['reg_id'];
				$paidloy['fname'] = $loc['first_name'];
				$paidloy['lname'] = $loc['last_name'];
				
				/* cumulative total */
				$periods = array();
				$sumloy = 0;
				while ($tmp_startdate < $enddate) {
					$tmp_enddate = $royalty->get_next_period($tmp_startdate, '+6');
					$tmp_endmonth = $royalty->get_next_period($tmp_startdate, '+5'); 
	
//var_dump($tmp_startdate->format('Y-m-d').'_'.$tmp_enddate->format('Y-m-d'));
					
					$period = $tmp_startdate->format('M').' - '
							.$tmp_endmonth->format('M').' '
							.$tmp_startdate->format('Y'); 
					
					$periods[] = $period;
					$loy = $royalty->get_royalty($tmp_startdate->format('Y'),
										$tmp_startdate->format('m'), 
										$tmp_endmonth->format('Y'),
										$tmp_endmonth->format('m'));
					$paidloy[$period] = $loy;
					$sumloy += $loy;
					$tmp_startdate = clone $tmp_enddate;
				}
				$_SESSION['periods'] = $periods;
				
				$col = new royalty_summary();
				$col->royalty = $sumloy;
				$paidloy['loyalty'] = $sumloy;
				
				$col->adjustment = $royalty->get_adjustment_check($startdate->format('Y-m-d'),
																  $enddate->format('Y-m-d'), '2');
				$paidloy['adjustment'] = $col->adjustment;
				
				$paidloy['total'] = $col->total;
				
				$col->paidcheck = $royalty->get_adjustment_check($startdate->format('Y-m-d'),
																 $enddate->format('Y-m-d'), '1');
				$paidloy['paidcheck'] = $col->paidcheck;
				
				$paidloy['accrued'] = $col->accrued;
				
				$_SESSION['paidloys'][] = $paidloy;
				if (!$col->is_empty()) $_SESSION['isEmpty'] = false;
				
/*break;*/		
			}
			
//if ($idx=='52') {var_dump($_SESSION['periods']); var_dump($_SESSION['paidloys']);}
 			
			//next localizer
			$idx += 1;
			page_redirect("royalty/?p=paid_localizer&idx=$idx&startdate=" . $startdate->format('Y-m-d') . "&enddate=" . $enddate->format('Y-m-d'));
	     }	
		else
		{    
		//export			
		//var_dump($_SESSION['periods']);
		//var_dump($_SESSION['paidloys']);

			
			/* write to excel */
            if ($_SESSION['isEmpty'])
            {
                $message = 'There is no data.';
            }
            else
            {        	            	
        		ini_set( 'memory_limit', '512M' );
        		require_once('/home/dmi/public_html/libs/PHPExcel/PHPExcel.php');
        		require_once('/home/dmi/public_html/libs/PHPExcel/PHPExcel/Writer/Excel5.php');
                $file_name = "RoyaltyPaidLocalizerReport.xls";

        		$objPHPExcel = new PHPExcel();
        		$objWriter = new PHPExcel_Writer_Excel5($objPHPExcel);
        		$objWorksheet = $objPHPExcel->getActiveSheet();

        		if($objWriter)
        		{
        			function is_empty_period($p,$ps,$pls){       			
        				 if (is_period($p, $ps)){
        					$empty = true;
		            		foreach ($pls as $pl){
		            		   if (!($pl[$p] == 0)){
		            		   		$empty = false;
		            		   		break;	
		            		   }
		            		}
        				 } else $empty = false;
		            	 return $empty;
		            }
        			
		            function is_period($p,$ps){
		            	$result = false;
        				foreach ($ps as $pn){
        					if ($pn == $p) {
        						$result=true;
        						break;
        					}
        				}
        				return $result;
		            }
        			
        			$j=0;
        			$objWorksheet->setCellValueByColumnAndRow( $j++,1, 'REGID' );
        			$objWorksheet->setCellValueByColumnAndRow( $j++,1, 'First Name' );
        			$objWorksheet->setCellValueByColumnAndRow( $j++,1, 'Last Name' );
        			foreach ($_SESSION['periods'] as $period)
        				if(!is_empty_period($period,$_SESSION['periods'],$_SESSION['paidloys']))
        					$objWorksheet->setCellValueByColumnAndRow( $j++,1, $period );
        				
        			$objWorksheet->setCellValueByColumnAndRow( $j++,1, 'Cumulative Total' );
        			$objWorksheet->setCellValueByColumnAndRow( $j++,1, 'Adjustment' );
        			$objWorksheet->setCellValueByColumnAndRow( $j++,1, 'Total' );
        			$objWorksheet->setCellValueByColumnAndRow( $j++,1, 'Paid Check' );
        			$objWorksheet->setCellValueByColumnAndRow( $j++,1, 'Accrued' );
        			
        			$rowCount = 2;
        			foreach ($_SESSION['paidloys'] as $paidloy) {
        				$j=0;
        				foreach ($paidloy as $key => $val)
        					if(!is_empty_period($key,$_SESSION['periods'],$_SESSION['paidloys']))
        						$objWorksheet->setCellValueByColumnAndRow( $j++,$rowCount, $val );
        				$rowCount++;
        			}
        			
        			
        			$objWriter->save($file_name);
        			header('Content-type: application/vnd.ms-excel');
        			header('Content-Disposition: attachment; filename="'.$file_name.'"');
        			readfile($file_name);
        			exit();
        		}
            }
		  }
		}
		$smarty->assign('message', $message);
		$tpl = 'royalty_paid_localizer.tpl';

		break;

	case 'currency': /* mod ken 3-26-2013 */
		function get_currency(){
			global $dmi_db;
			$sql  = ' SELECT CASE WHEN a.platform_id = 0 THEN \'All\' ELSE b.name END AS platform ';
	        $sql .= '       ,a.currency ';
	        $sql .= '       ,a.rate,a.currency_id,a.sort_order ';
	        $sql .= '   FROM royalty_currency AS a ';
	        $sql .= '   LEFT JOIN royalty_platform AS b ';
	        $sql .= '     ON a.platform_id = b.platform_id ';
	        $sql .= '  ORDER BY a.sort_order ';
	
			$currency = $dmi_db->fetch_all2($sql);
			return $currency;
		}
		
		function move_currency($from,$to,$currency){
			global $dmi_db;
			/*var_dump($from.'->'.$to);*/
			$sql = 'update royalty_currency set sort_order='.$currency[$to]['sort_order']
				   .' where currency_id='.$currency[$from]['currency_id'];
			$dmi_db->query($sql);

			$sql = 'update royalty_currency set sort_order='.$currency[$from]['sort_order']
				   .' where currency_id='.$currency[$to]['currency_id'];
			$dmi_db->query($sql);
		}
			
		$sort_order = $_POST['sort_order'][0];
		
		$currency = get_currency();
      	
		
		if (!is_null($sort_order)){
			$idx = intval($sort_order);
			if ($_POST['action']=='move up' && $sort_order>0){
					move_currency($idx,$idx-1,$currency);
					$currency = get_currency();	
					$idx--;
			}elseif ($_POST['action']=='move down' && $sort_order<count($currency)-1){
					move_currency($idx,$idx+1,$currency);
					$currency = get_currency();	
					$idx++;
			}
		}

		$smarty->assign('idx_checked', is_null($sort_order) ?  -1 : $idx);
		
		/* mod 3-22-2013 */
		$smarty->assign('readonly', !check_access( 'royalty', 2, false ));
	
        //assign
        $smarty->assign('currency', $currency);
		$tpl = 'royalty_currency.tpl';

		break;
	
	case 'edit_currency': /* add ken 3-22-2013 */
		
		$currencies = $dmi_db->fetch_all2('select distinct currency from royalty_currency order by currency');
				
		$platforms = $dmi_db->fetch_all2('select * from royalty_platform order by name');
		
		if (isset($_GET['cid'])) {
			$current = $dmi_db->fetch_all2('select * from royalty_currency where currency_id = '.$_GET['cid']);
		}

		$smarty->assign('cid',$_GET['cid']);
		$smarty->assign('current',$current);
		$smarty->assign('currencies',$currencies);
        $smarty->assign('platforms', $platforms);
		
		
		
		$tpl = 'royalty_currency_edit.tpl';
		break;
		
	case 'save_currency': /* add ken 3-25-2013 */
		$cid = $_GET['cid'];
		$platform_id = $_POST['platform_id'];
		$currency = trim($_POST['currency']); /* mod 8-16-2013 */
		$rate = trim($_POST['rate']);
			
		if ($cid == '0'){
			$current = $dmi_db->fetch_all2("select * from royalty_currency where currency = '$currency' and platform_id = $platform_id");
			if ($current) {
				send_message_page( '/royalty/?p=currency', 'Error: The currency rate of this platform already exist.');
			}else{
				$max_sort_order = $dmi_db->fetch_all2("select max(sort_order) as mso from royalty_currency");
				$sql = "insert into royalty_currency (currency,rate,platform_id,sort_order,create_date,update_date) "
					 ." values ('$currency',$rate,$platform_id,".($max_sort_order[0]['mso']+1).",now(),now())";
				
				$dmi_db->query($sql);
				send_message_page( '/royalty/?p=currency', 'The currency rate has been added successfully.');
			}
		}else{
			$sql = "update royalty_currency set rate = $rate,update_date = now() where currency_id = $cid";

			$dmi_db->query($sql);
			send_message_page( '/royalty/?p=currency', 'The currency rate has been updated successfully.');	 
		}
		
		break;
		
	case 'del_currency': /* add ken 3-25-2013 */
		$cid = $_GET['cid'];
		$sql = "delete from royalty_currency where currency_id = $cid";

		$dmi_db->query($sql);
		send_message_page( '/royalty/?p=currency', 'The currency rate has been deleted successfully.');	 
		
		break;
		

	default:
		break;
}

$sidemenu = $submenus['Royalty'];

function get_currency_name()
{
	global $dmi_db;
	$sql  = ' SELECT * FROM royalty_currency_name ';
        $sql .= '  ORDER BY currency ';

	$currency = $dmi_db->fetch_all2($sql);
	return $currency;
}
				

?>

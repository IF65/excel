<?php
	//@ini_set('memory_limit','8192M');

	require '../vendor/autoload.php';
	// leggo i dati da un file
    //$request = file_get_contents('../examples/rivalutazioni.json');
    $request = file_get_contents('php://input');
    $data = json_decode($request, true);

    use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
    use PhpOffice\PhpSpreadsheet\Cell\DataType;
    use PhpOffice\PhpSpreadsheet\Spreadsheet;
    use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
    use PhpOffice\PhpSpreadsheet\Style\Style;
    use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
    use PhpOffice\PhpSpreadsheet\Style\Alignment;
    use PhpOffice\PhpSpreadsheet\Style\Fill;
    use PhpOffice\PhpSpreadsheet\Style\Border;
	use PhpOffice\PhpSpreadsheet\Shared\Date;
	use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

	// verifico l'esistenza della cartella temp e se serve la creo
	// con mask 777.
	if (! file_exists ( '../temp' )) {
		$oldMask = umask(0);
		mkdir('../temp', 0777);
		umask($oldMask);
	}

    $style = new Style();

    // leggo i parametri contenuti nel file
    $nomeFile = $data['nomeFile'];
    $file = '../temp/'.$nomeFile.'.xlsx';

    $rivalutazioni = $data['rivalutazioni'];
    $ordinamento = array();
    foreach ($rivalutazioni as $key => $row) {
        $ordinamento[$key] = $row['numero'];
    }
    array_multisort($ordinamento, SORT_ASC, $rivalutazioni);

    // creazione del workbook
    $workBook = new Spreadsheet();
    $workBook->getDefaultStyle()->getFont()->setName('Arial');
    $workBook->getDefaultStyle()->getFont()->setSize(12);
    $workBook->getProperties()
        ->setCreator("IF65 S.p.A. (Gruppo Italmark)")
        ->setLastModifiedBy("IF65 S.p.A.")
        ->setTitle("Rivalutazioni")
        ->setSubject("Rivalutazioni")
        ->setDescription("Esportazione Rivalutazioni")
        ->setKeywords("office 2007 openxml php")
        ->setCategory("SM Docs");

    // creazione degli Sheet (uno per ogni ordine)
    $sheetNumber = 0;
    foreach ($rivalutazioni as $rivalutazione) {
        $sheetNumber++;
        if ($workBook->getSheetCount() < $sheetNumber) {
            $workBook->createSheet();
        }
        $sheet = $workBook->setActiveSheetIndex($sheetNumber-1); // la numerazione dei worksheet parte da 0
        $sheet->setTitle(preg_replace('/\//','_',$rivalutazione['numero']));

		$timeZone = new DateTimeZone('Europe/Rome');

		$data = new \DateTime($rivalutazione['data']);

        // riquadro di testata
        // --------------------------------------------------------------------------------
        $sheet->setCellValue('A1', strtoupper('numero'));
        $sheet->setCellValue('B1', $rivalutazione['numero']);
        $sheet->mergeCells('B1:H1');
    	$sheet->setCellValue('A2',strtoupper('data'));
        $sheet->setCellValue('B2', Date::PHPToExcel($data->setTimezone($timeZone)->format('Y-m-d')));
		$sheet->getStyle('B2')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_DATE_DDMMYYYY);
		$sheet->mergeCells('B2:H2');
        $sheet->setCellValue('A3', strtoupper('descrizione'));
        $sheet->setCellValue('B3', $rivalutazione['descrizione']);
        $sheet->mergeCells('B3:H3');
        $sheet->setCellValue('A4', strtoupper('fornitore'));
		$sheet->setCellValue('B4', $rivalutazione['fornitore']);
        $sheet->mergeCells('B4:H4');
        $sheet->setCellValue('A5', strtoupper('linea'));
		$sheet->setCellValue('B5', $rivalutazione['linea']);
		$sheet->mergeCells('B5:H5');
        $sheet->setCellValue('A6', strtoupper('valore'));
        $sheet->mergeCells('B6:H6');
        $sheet->mergeCells('A7:H7');

        // testata colonne
        // --------------------------------------------------------------------------------
        $sheet->setCellValue('A8', strtoupper('cod. art.'));
        $sheet->setCellValue('B8', strtoupper('cod. art. forn.'));
        $sheet->setCellValue('C8', strtoupper('barcode'));
        $sheet->setCellValue('D8', strtoupper('descrizione'));
        $sheet->setCellValue('E8', strtoupper('modello'));
        $sheet->setCellValue('F8', strtoupper('giacenza'));
		$sheet->setCellValue('G8', strtoupper('val. un.'));
        $sheet->setCellValue('H8', strtoupper('valore'));

        // scrittura righe
        // --------------------------------------------------------------------------------
        $primaRigaDati = 9; // attenzione le righe in Excel partono da 1

        $righe = $rivalutazione['articoli'];
    	$ordinamento = array();
    	foreach ($righe as $key => $row) {
        	$ordinamento[$key] = $row['codiceArticolo'];
    	}
    	array_multisort($ordinamento, SORT_ASC, $righe);

        for ($i = 0; $i < count($righe); $i++) {
            $R = ($i+$primaRigaDati);
            
            // formule
            $valore = "=ROUND(F$R*G$R,2)";
            
            // righe
			$sheet->getCell('A'.$R)->setValueExplicit($righe[$i]['codiceArticolo'],DataType::TYPE_STRING);
            $sheet->getCell('B'.$R)->setValueExplicit($righe[$i]['codiceArticoloFornitore'],DataType::TYPE_STRING);
            $sheet->getCell('C'.$R)->setValueExplicit($righe[$i]['barcode'],DataType::TYPE_STRING);
            $sheet->getCell('D'.$R)->setValueExplicit($righe[$i]['descrizioneArticolo'],DataType::TYPE_STRING);
            $sheet->getCell('E'.$R)->setValueExplicit($righe[$i]['modelloArticolo'],DataType::TYPE_STRING);
            $sheet->getCell('F'.$R)->setValueExplicit($righe[$i]['giacenza'],DataType::TYPE_NUMERIC);
            $sheet->getCell('G'.$R)->setValueExplicit($righe[$i]['valoreUnitario'],DataType::TYPE_NUMERIC);
            $sheet->getCell('H'.$R)->setValueExplicit($valore,DataType::TYPE_FORMULA);
        }

        // riquadro di testata (formule)
        // --------------------------------------------------------------------------------
        $highestRow = $sheet->getHighestRow();
		$highestColumn = $sheet->getHighestColumn();
		
        $totale = "=SUM(H$primaRigaDati:H$highestRow)";
        $sheet->getCell('B6')->setValueExplicit($totale,DataType::TYPE_FORMULA);
        $sheet->getStyle("B6")->getNumberFormat()->setFormatCode('###,###,##0.00;[Red][<0]-###,###,##0.00;');
    
        // formattazione
        // --------------------------------------------------------------------------------
		$lastCellAddress = "$highestColumn$highestRow";
		$sheet->getStyle('A1:'.$lastCellAddress)->getAlignment()->setVertical('center');
		
        $sheet->getDefaultRowDimension()->setRowHeight(20);
        $sheet->setShowGridlines(true);

        // riquadro di testata
        $sheet->getStyle('A1:H7')->getAlignment()->setHorizontal('left');
		$sheet->getStyle('A1:A7')->getFont()->setBold(true);
        
		// testata colonne
		$sheet->getStyle('A8:H8')->getAlignment()->setHorizontal('center');
		$sheet->getStyle('A8:H8')->getFont()->setBold(true);

        $sheet->getStyle("F$primaRigaDati:F$highestRow")->getNumberFormat()->setFormatCode('###,###,##0;[Red][<0]-###,###,##0;');
		$sheet->getStyle("G$primaRigaDati:G$highestRow")->getNumberFormat()->setFormatCode('###,###,##0.00;[Red][<0]-###,###,##0.00;');
		$sheet->getStyle("H$primaRigaDati:H$highestRow")->getNumberFormat()->setFormatCode('###,###,##0.00;[Red][<0]-###,###,##0.00;');
		
		$sheet->getStyle("A$primaRigaDati:A$highestRow")->getAlignment()->setHorizontal('center');
		$sheet->getStyle("B$primaRigaDati:B$highestRow")->getAlignment()->setHorizontal('center');
		$sheet->getStyle("C$primaRigaDati:C$highestRow")->getAlignment()->setHorizontal('center');
		$sheet->getStyle("F$primaRigaDati:F$highestRow")->getAlignment()->setHorizontal('center');
		$sheet->getStyle("G$primaRigaDati:G$highestRow")->getAlignment()->setHorizontal('right');
		$sheet->getStyle("H$primaRigaDati:H$highestRow")->getAlignment()->setHorizontal('right');

        // larghezza colonne (non uso volutamente autowidth)
        $sheet->getColumnDimension('A')->setWidth(15);
        $sheet->getColumnDimension('B')->setWidth(20);
        $sheet->getColumnDimension('C')->setWidth(25);
        $sheet->getColumnDimension('D')->setWidth(60);
        $sheet->getColumnDimension('E')->setWidth(20);
        $sheet->getColumnDimension('F')->setWidth(12);
        $sheet->getColumnDimension('G')->setWidth(12);
        $sheet->getColumnDimension('H')->setWidth(12);
        
        $rigaTitoli = $primaRigaDati - 1;
        $styleArray = array(
        	'borders' => array(
            	'outline' => array(
                	'borderStyle' => Border::BORDER_THIN,
                	'color' => array('argb' => 'FF0000FF'),
            	),
        	),
    	);
    	$sheet ->getStyle("A$rigaTitoli:H$rigaTitoli")->applyFromArray($styleArray);
        $sheet ->getStyle("A$rigaTitoli:A$highestRow")->applyFromArray($styleArray);
        $sheet ->getStyle("B$rigaTitoli:B$highestRow")->applyFromArray($styleArray);
        $sheet ->getStyle("C$rigaTitoli:C$highestRow")->applyFromArray($styleArray);
        $sheet ->getStyle("D$rigaTitoli:D$highestRow")->applyFromArray($styleArray);
        $sheet ->getStyle("E$rigaTitoli:E$highestRow")->applyFromArray($styleArray);
        $sheet ->getStyle("F$rigaTitoli:F$highestRow")->applyFromArray($styleArray);
        $sheet ->getStyle("G$rigaTitoli:G$highestRow")->applyFromArray($styleArray);
        $sheet ->getStyle("H$rigaTitoli:H$highestRow")->applyFromArray($styleArray);
		
		$sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
		$sheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_A4);
		$sheet->getPageSetup()->setFitToWidth(1);
		$sheet->getPageSetup()->setFitToHeight(0);
		$sheet->getPageSetup()->setHorizontalCentered(true);
		$sheet->getPageSetup()->setVerticalCentered(false);
		$sheet->getHeaderFooter()->setOddHeader('&C&HDocumento confidenziale!');
		$sheet->getHeaderFooter()->setOddFooter('&L&B' . $workBook->getProperties()->getTitle() . '&RPage &P of &N');
        
        $workBook->setActiveSheetIndex(0);
	}

    $writer = new Xlsx($workBook);
    $writer->save($file);

    if (file_exists($file)) {
		header('Content-Description: File Transfer');
		header('Content-Type: application/octet-stream');
		header('Content-Disposition: attachment; filename="'.basename($file).'"');
		header('Expires: 0');
		header('Cache-Control: must-revalidate');
		header('Pragma: public');
		header('Content-Length: ' . filesize($file));
		readfile($file);
		exit;
	}

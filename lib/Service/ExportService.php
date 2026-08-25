<?php

declare(strict_types=1);

namespace OCA\ProjectManager\Service;

use OCA\ProjectManager\Db\Feature;
use OCA\ProjectManager\Db\TestEntry;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Builds a .xlsx workbook reproducing the original Excel tracker's three
 * sheets (Project grid, Features, Tests), colors and number formats.
 */
class ExportService {
	private const COLOR_HEADER = '305496';
	private const COLOR_POINT = 'D9E1F2';
	private const COLOR_SUMMARY = 'FFE699';
	private const COLOR_STATUS_TODO = 'F2F2F2';
	private const COLOR_STATUS_IN_PROGRESS = 'FFF2CC';
	private const COLOR_STATUS_PARTIAL = 'FCE4D6';
	private const COLOR_STATUS_DONE = 'E2EFDA';
	private const COLOR_STATUS_FAILED = 'FCE4D6';

	public function __construct(
		private TrackerService $trackerService,
		private FeatureService $featureService,
		private TestService $testService,
	) {
	}

	public function build(int $projectId, string $userId): Spreadsheet {
		$grid = $this->trackerService->buildGrid($projectId, $userId);
		$features = $this->featureService->findAll($projectId, $userId);
		$tests = $this->testService->findAll($projectId, $userId);

		$spreadsheet = new Spreadsheet();
		$this->buildProjectSheet($spreadsheet->getActiveSheet(), $grid);
		$this->buildFeaturesSheet($spreadsheet->createSheet(), $features);
		$this->buildTestsSheet($spreadsheet->createSheet(), $tests);
		$spreadsheet->setActiveSheetIndex(0);

		return $spreadsheet;
	}

	/** Sets a cell value by 1-based column index + row number. */
	private function setCell(Worksheet $sheet, int $col, int $row, mixed $value): void {
		$sheet->setCellValue([$col, $row], $value);
	}

	private function headerStyle(Worksheet $sheet, string $range): void {
		$sheet->getStyle($range)->applyFromArray([
			'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
			'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_HEADER]],
			'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
		]);
	}

	private function fillStyle(Worksheet $sheet, string $range, string $rgb): void {
		$sheet->getStyle($range)->applyFromArray([
			'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $rgb]],
		]);
	}

	private function statusColor(string $status): string {
		return match ($status) {
			'in_progress' => self::COLOR_STATUS_IN_PROGRESS,
			'partial' => self::COLOR_STATUS_PARTIAL,
			'done', 'passed' => self::COLOR_STATUS_DONE,
			'failed' => self::COLOR_STATUS_FAILED,
			default => self::COLOR_STATUS_TODO,
		};
	}

	private function buildProjectSheet(Worksheet $sheet, array $grid): void {
		$sheet->setTitle('Project');
		$days = $grid['days'];
		$fixedCols = ['Point', 'Module', 'Description', 'Est.(h)', 'Done(h)', 'Rem.(h)', 'Status'];
		$fixedColCount = count($fixedCols);

		// Header row
		foreach ($fixedCols as $i => $label) {
			$this->setCell($sheet, $i + 1, 1, $label);
		}
		foreach ($days as $i => $day) {
			$this->setCell($sheet, $fixedColCount + 1 + $i, 1, $day);
		}
		$lastCol = $fixedColCount + count($days);
		$this->headerStyle($sheet, 'A1:' . $this->colLetter($lastCol) . '1');

		// Hours/day row
		$row = 2;
		$this->setCell($sheet, 3, $row, 'Hours/day');
		foreach ($days as $i => $day) {
			$this->setCell($sheet, $fixedColCount + 1 + $i, $row, $grid['hoursByDay'][$day] ?? 0.0);
		}
		$sheet->getStyle("A{$row}:{$this->colLetter($lastCol)}{$row}")->getFont()->setBold(true);
		$row++;

		foreach ($grid['modules'] as $module) {
			$this->setCell($sheet, 2, $row, $module['code']);
			$this->setCell($sheet, 3, $row, $module['name']);
			$this->setCell($sheet, 4, $row, $module['estimateH']);
			$this->setCell($sheet, 5, $row, $module['doneH']);
			$this->setCell($sheet, 6, $row, $module['remainingH']);
			foreach ($days as $i => $day) {
				$pct = $module['pctByDay'][$day] ?? 0;
				if ($pct > 0) {
					$this->setCell($sheet, $fixedColCount + 1 + $i, $row, $pct / 100);
				}
			}
			$sheet->getStyle("A{$row}:{$this->colLetter($lastCol)}{$row}")->getFont()->setBold(true);
			$row++;

			foreach ($module['points'] as $point) {
				$this->setCell($sheet, 1, $row, $point['code']);
				$this->setCell($sheet, 3, $row, $point['description']);
				$this->setCell($sheet, 4, $row, $point['estimateH']);
				$this->setCell($sheet, 5, $row, $point['doneH']);
				$this->setCell($sheet, 6, $row, $point['remainingH']);
				$this->setCell($sheet, 7, $row, $point['status']);
				foreach ($days as $i => $day) {
					$pct = $point['pctByDay'][$day] ?? 0;
					if ($pct > 0) {
						$this->setCell($sheet, $fixedColCount + 1 + $i, $row, $pct / 100);
					}
				}
				$this->fillStyle($sheet, "A{$row}:{$this->colLetter($fixedColCount)}{$row}", self::COLOR_POINT);
				$this->fillStyle($sheet, "G{$row}", $this->statusColor($point['status']));
				$row++;

				foreach ($point['leaves'] as $leaf) {
					$this->setCell($sheet, 3, $row, '    ' . $leaf['description']);
					$dayIndex = array_search($leaf['workDate'], $days, true);
					if ($dayIndex !== false) {
						$this->setCell($sheet, $fixedColCount + 1 + $dayIndex, $row, 'X');
					}
					$row++;
				}
			}
		}

		// TOTAL/DAY row
		$this->setCell($sheet, 3, $row, 'TOTAL/DAY');
		foreach ($days as $i => $day) {
			$total = $grid['totalPctByDay'][$day] ?? 0;
			$this->setCell($sheet, $fixedColCount + 1 + $i, $row, $total / 100);
		}
		$this->fillStyle($sheet, "A{$row}:{$this->colLetter($lastCol)}{$row}", self::COLOR_SUMMARY);
		$sheet->getStyle("A{$row}:{$this->colLetter($lastCol)}{$row}")->getFont()->setBold(true);
		$row += 2;

		// SUMMARY block
		$summary = $grid['summary'];
		$summaryRows = [
			['Estimated (P1-P4)', $summary['estimatedH'], $summary['estimatedDays']],
			['Done (P1-P4)', $summary['doneInScopeH'], $summary['doneInScopeDays']],
			['Done (OTHERS)', $summary['doneOthersH'], $summary['doneOthersDays']],
			['Done (TOTAL)', $summary['doneTotalH'], $summary['doneTotalDays']],
			['Remaining (P1-P4)', $summary['remainingH'], $summary['remainingDays']],
		];
		$this->setCell($sheet, 3, $row, 'SUMMARY');
		$sheet->getStyle("C{$row}")->getFont()->setBold(true);
		$row++;
		foreach ($summaryRows as [$label, $hours, $days2]) {
			$this->setCell($sheet, 3, $row, $label);
			$this->setCell($sheet, 4, $row, $hours);
			$this->setCell($sheet, 5, $row, $days2);
			$this->fillStyle($sheet, "C{$row}:E{$row}", self::COLOR_SUMMARY);
			$row++;
		}

		// Number formats
		$dayColRange = $this->colLetter($fixedColCount + 1) . '3:' . $this->colLetter($lastCol) . ($row - 1);
		$sheet->getStyle($dayColRange)->getNumberFormat()->setFormatCode('0.00%');
		$sheet->getStyle('D3:F' . ($row - 1))->getNumberFormat()->setFormatCode('0.00"h"');

		// Layout
		$sheet->getColumnDimension('A')->setWidth(10);
		$sheet->getColumnDimension('B')->setWidth(10);
		$sheet->getColumnDimension('C')->setWidth(48);
		foreach (['D', 'E', 'F'] as $col) {
			$sheet->getColumnDimension($col)->setWidth(10);
		}
		$sheet->getColumnDimension('G')->setWidth(14);
		for ($i = 0; $i < count($days); $i++) {
			$sheet->getColumnDimension($this->colLetter($fixedColCount + 1 + $i))->setWidth(9);
		}
		$sheet->freezePane('D3');
	}

	/** @param Feature[] $features */
	private function buildFeaturesSheet(Worksheet $sheet, array $features): void {
		$sheet->setTitle('Features');
		$headers = ['Feature', 'Point', 'Status', 'Business value', 'External pending'];
		foreach ($headers as $i => $label) {
			$this->setCell($sheet, $i + 1, 1, $label);
		}
		$this->headerStyle($sheet, 'A1:E1');

		$row = 2;
		$currentSection = null;
		foreach ($features as $feature) {
			if ($feature->getSection() !== $currentSection) {
				$currentSection = $feature->getSection();
				$this->setCell($sheet, 1, $row, $currentSection);
				$this->fillStyle($sheet, "A{$row}:E{$row}", self::COLOR_SUMMARY);
				$sheet->getStyle("A{$row}")->getFont()->setBold(true);
				$row++;
			}
			$this->setCell($sheet, 1, $row, $feature->getName());
			$this->setCell($sheet, 2, $row, $feature->getPointRef());
			$this->setCell($sheet, 3, $row, $feature->getStatus());
			$this->setCell($sheet, 4, $row, $feature->getBusinessValue());
			$this->setCell($sheet, 5, $row, $feature->getExternalPending());
			$this->fillStyle($sheet, "C{$row}", $this->statusColor($feature->getStatus()));
			$row++;
		}

		foreach (['A' => 30, 'B' => 12, 'C' => 16, 'D' => 40, 'E' => 32] as $col => $width) {
			$sheet->getColumnDimension($col)->setWidth($width);
		}
		$sheet->freezePane('A2');
	}

	/** @param TestEntry[] $tests */
	private function buildTestsSheet(Worksheet $sheet, array $tests): void {
		$sheet->setTitle('Tests');
		$headers = ['ID', 'Area', 'Profile', 'Scenario/Action', 'Expected result', 'Status', 'Date', 'Notes'];
		foreach ($headers as $i => $label) {
			$this->setCell($sheet, $i + 1, 1, $label);
		}
		$this->headerStyle($sheet, 'A1:H1');

		$row = 2;
		$counts = ['passed' => 0, 'failed' => 0, 'to_test' => 0];
		foreach ($tests as $test) {
			$this->setCell($sheet, 1, $row, $test->getId());
			$this->setCell($sheet, 2, $row, $test->getArea());
			$this->setCell($sheet, 3, $row, $test->getProfile());
			$this->setCell($sheet, 4, $row, $test->getScenario());
			$this->setCell($sheet, 5, $row, $test->getExpected());
			$this->setCell($sheet, 6, $row, $test->getStatus());
			$this->setCell($sheet, 7, $row, $test->getTestDate()?->format('Y-m-d'));
			$this->setCell($sheet, 8, $row, $test->getNotes());
			$this->fillStyle($sheet, "F{$row}", $this->statusColor($test->getStatus()));
			$counts[$test->getStatus()] = ($counts[$test->getStatus()] ?? 0) + 1;
			$row++;
		}

		$row++;
		$this->setCell($sheet, 1, $row, 'SUMMARY');
		$this->setCell($sheet, 2, $row, sprintf('Passed: %d  Failed: %d  To test: %d', $counts['passed'], $counts['failed'], $counts['to_test']));
		$this->fillStyle($sheet, "A{$row}:H{$row}", self::COLOR_SUMMARY);
		$sheet->getStyle("A{$row}")->getFont()->setBold(true);

		foreach (['A' => 8, 'B' => 16, 'C' => 16, 'D' => 40, 'E' => 40, 'F' => 12, 'G' => 12, 'H' => 30] as $col => $width) {
			$sheet->getColumnDimension($col)->setWidth($width);
		}
		$sheet->freezePane('A2');
	}

	private function colLetter(int $index): string {
		return \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index);
	}
}

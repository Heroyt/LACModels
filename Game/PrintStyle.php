<?php
/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\GameModels\Game;

use App\Tools\Color;
use Dibi\DateTime;
use Lsr\Core\DB;
use Lsr\Core\Exceptions\ModelNotFoundException;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Models\Attributes\PrimaryKey;
use Lsr\Core\Models\Model;

#[PrimaryKey('id_style')]
class PrintStyle extends Model
{

	public const TABLE = 'print_styles';

	public const COLORS  = ['dark', 'light', 'primary'];
	public const CLASSES = ['text', 'bg', ''];
	public static bool $gotVars      = false;
	public string      $name         = '';
	public string      $colorDark    = '';
	public string      $colorLight   = '';
	public string      $colorPrimary = '';
	public string      $bg           = '';
	public string      $bg_landscape = '';
	public bool        $default      = false;

	/**
	 * @return PrintStyle|null
	 * @throws ModelNotFoundException
	 * @throws ValidationException
	 */
	public static function getActiveStyle() : ?PrintStyle {
		$id = self::getActiveStyleId();
		if ($id > 0) {
			return new self($id);
		}
		return null;
	}


	/**
	 * @return int
	 */
	public static function getActiveStyleId() : int {
		$currentStyle = DB::select(self::TABLE.'_dates', 'id_style')
											->where('DAYOFYEAR(CURDATE()) BETWEEN DAYOFYEAR(date_from) AND DAYOFYEAR(date_to)')
											->fetchSingle();
		if (isset($currentStyle)) {
			return $currentStyle;
		}
		$defaultStyle = DB::select(self::TABLE, '[id_style]')->where('[default] = 1')->fetchSingle();
		return $defaultStyle ?? 0;
	}


	/**
	 * @return array{style:PrintStyle,from:DateTime,to:DateTime}[]
	 * @throws ModelNotFoundException
	 * @throws ValidationException
	 */
	public static function getAllStyleDates() : array {
		$styles = DB::select(self::TABLE.'_dates', '*')->fetchAll();
		$return = [];
		foreach ($styles as $style) {
			$return[] = [
				'style' => self::get($style->id_style),
				'from'  => $style->date_from,
				'to'    => $style->date_to,
			];
		}
		return $return;
	}

	/**
	 * @param bool $tag
	 *
	 * @return string
	 */
	public function getCssClasses(bool $tag = true) : string {
		$return = '';
		if ($tag) {
			$return .= '<style>';
		}
		if (!self::$gotVars) {
			$return .= ':root {'.$this->getCssVars(false).'}';
		}
		foreach (self::COLORS as $color) {
			$return .= '.print-'.$color.' {--text-color: var(--print-'.$color.'-text); background-color: var(--print-'.$color.') !important; color: var(--print-'.$color.'-text) !important;}';
			$return .= '.bg-print-'.$color.' {background-color: var(--print-'.$color.') !important;}';
			$return .= '.text-print-'.$color.' {--text-color: var(--print-'.$color.'-text); color: var(--print-'.$color.') !important;}';
		}
		if ($tag) {
			$return .= '</style>';
		}
		return $return;
	}

	/**
	 * @param bool $tag
	 *
	 * @return string
	 */
	public function getCssVars(bool $tag = true) : string {
		$return = '';
		if ($tag) {
			$return .= '<style>:root {';
		}
		$return .= '--print-dark: '.$this->colorDark.';--print-light: '.$this->colorLight.';--print-primary: '.$this->colorPrimary.';';
		$return .= '--print-dark-text: '.Color::getFontColor($this->colorDark).';--print-light-text: '.Color::getFontColor($this->colorLight).';--print-primary-text: '.Color::getFontColor($this->colorPrimary).';';
		if ($tag) {
			$return .= '}</style>';
		}
		self::$gotVars = true;
		return $return;
	}

}
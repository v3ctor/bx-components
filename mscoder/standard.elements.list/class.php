<?
if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use \Bitrix\Main;
use \Bitrix\Main\Localization\Loc as Loc;

class StandardElementListComponent extends CBitrixComponent
{
	/**
	 * кешируемые ключи arResult
	 * @var array()
	 */
	protected $cacheKeys = array();
	
	/**
	 * дополнительные параметры, от которых должен зависеть кеш
	 * @var array
	 */
	protected $cacheAddon = array();
	
	/**
	 * парамтеры постраничной навигации
	 * @var array
	 */
	protected $navParams = array();
	
	/**
	 * подключает языковые файлы
	 */
	public function onIncludeComponentLang()
	{
		$this -> includeComponentLang(basename(__FILE__));
		Loc::loadMessages(__FILE__);
	}
	
    /**
     * подготавливает входные параметры
     * @param array $arParams
     * @return array
     */
    public function onPrepareComponentParams($params)
    {
        $result = array(
            'IBLOCK_TYPE' => trim($params['IBLOCK_TYPE']),
            'IBLOCK_ID' => intval($params['IBLOCK_ID']),
            'SHOW_NAV' => ($params['SHOW_NAV'] == 'Y' ? 'Y' : 'N'),
            'COUNT' => intval($params['COUNT']),
            'SORT_FIELD1' => strlen($params['SORT_FIELD1']) ? $params['SORT_FIELD1'] : 'ID',
            'SORT_DIRECTION1' => $params['SORT_DIRECTION1'] == 'ASC' ? 'ASC' : 'DESC',
            'SORT_FIELD2' => strlen($params['SORT_FIELD2']) ? $params['SORT_FIELD2'] : 'ID',
            'SORT_DIRECTION2' => $params['SORT_DIRECTION2'] == 'ASC' ? 'ASC' : 'DESC',
            'CACHE_TIME' => intval($params['CACHE_TIME']) > 0 ? intval($params['CACHE_TIME']) : 3600
        );
        return $result;
    }
	
	/**
	 * определяет читать данные из кеша или нет
	 * @return bool
	 */
	protected function readDataFromCache()
	{
		if ($this -> arParams['CACHE_TYPE'] == 'N')
			return false;

		return !($this -> StartResultCache(false, $this -> cacheAddon));
	}

	/**
	 * кеширует ключи массива arResult
	 */
	protected function putDataToCache()
	{
		if (is_array($this -> cacheKeys) && sizeof($this -> cacheKeys) > 0)
		{
			$this -> SetResultCacheKeys($this -> cacheKeys);
		}
	}

	/**
	 * прерывает кеширование
	 */
	protected function abortDataCache()
	{
		$this -> AbortResultCache();
	}
	
	/**
	 * проверяет подключение необходиимых модулей
	 * @throws LoaderException
	 */
	protected function checkModules()
	{
		if (!Main\Loader::includeModule('iblock'))
			throw new Main\LoaderException(Loc::getMessage('STANDARD_ELEMENTS_LIST_CLASS_IBLOCK_MODULE_NOT_INSTALLED'));
	}
	
	/**
	 * проверяет заполнение обязательных параметров
	 * @throws SystemException
	 */
	protected function checkParams()
	{
		if ($this -> arParams['IBLOCK_ID'] <= 0)
			throw new Main\ArgumentNullException('IBLOCK_ID');
	}
	
	/**
	 * выполяет действия перед кешированием 
	 */
	protected function executeProlog()
	{
		if ($this -> arParams['COUNT'] > 0)
		{
			if ($this -> arParams['SHOW_NAV'] == 'Y')
			{
				\CPageOption::SetOptionString('main', 'nav_page_in_session', 'N');
				$this -> navParams = array(
					'nPageSize' => $this -> arParams['COUNT']
				);
	    		$arNavigation = \CDBResult::GetNavParams($this -> navParams);
				$this -> cacheAddon = array($arNavigation);
			}
			else
			{
				$this -> navParams = array(	
					'nTopCount' => $this -> arParams['COUNT']
				);
			}
		}
	}
	
	/**
	 * получение результатов
	 */
	protected function getResult()
	{
		$filter = array(
			'IBLOCK_TYPE' => $this -> arParams['IBLOCK_TYPE'],
			'IBLOCK_ID' => $this -> arParams['IBLOCK_ID'],
			'ACTIVE' => 'Y'
		);
		$sort = array(
			$this -> arParams['SORT_FIELD1'] => $this -> arParams['SORT_DIRECTION1'],
			$this -> arParams['SORT_FIELD2'] => $this -> arParams['SORT_DIRECTION2']
		);
		$select = array(
			'ID',
			'NAME',
			'DATE_ACTIVE_FROM',
			'DETAIL_PAGE_URL',
			'PREVIEW_TEXT',
			'PREVIEW_TEXT_TYPE'
		);
		$rsElement = \CIBlockElement::GetList($sort, $filter, false, $this -> navParams, $select);
		while ($arElement = $rsElement -> GetNext())
		{
			$this -> arResult['ITEMS'][] = array(
				'ID' => $arElement['ID'],
				'NAME' => $arElement['NAME'],
				'DATE' => $arElement['DATE_ACTIVE_FROM'],
				'URL' => $arElement['DETAIL_PAGE_URL'],
				'TEXT' => $arElement['PREVIEW_TEXT']
			);
		}
		if ($this -> arParams['SHOW_NAV'] == 'Y' && $this -> arParams['COUNT'] > 0)
		{
			$this -> arResult['NAV_STRING'] = $rsElement -> GetPageNavString('');
		}
	}
	
	/**
	 * выполняет действия после выполения компонента, например установка заголовков из кеша
	 */
	protected function executeEpilog()
	{
		
	}
	
	/**
	 * выполняет логику работы компонента
	 */
	public function executeComponent()
	{
		try
		{
			$this -> checkModules();
			$this -> checkParams();
			$this -> executeProlog();
			if (!$this -> readDataFromCache())
			{
				$this -> getResult();
				$this -> putDataToCache();
				$this -> includeComponentTemplate();
			}
			$this -> executeEpilog();
		}
		catch (Exception $e)
		{
			$this -> abortDataCache();
			ShowError($e -> getMessage());
		}
	}
}
?>
<?php
header("Content-type: text/plain");

/* Google Merchant Center Feed 
	customized for Prestashop 1.7 and German Shops 
*/

require(dirname(__FILE__) . '/../../config/config.inc.php');
$db = Db::getInstance();

// Vars, sind entsprechend zu ersetzen
/* TODO: get Google Category from Database */
$google_cat = "1772";
$need_active = 1;

/* TODO: get URL from Database */
$url = "https://www.your-shop.com/";
$trenner = "~";
$id_zusatz = "-de";
$lang_id = "1";

/* TODO: get shipping costs from Database */
$versand = "0.00";
$versand_frei_ab = 0;

// Kopf
echo '"id"~"titel"~"gtin"~"description"~"link"~"price"~"sale price"~"condition"~"image link"~"brand"~"availability"~"google product category"~"shipping"~"color"~"size"';
echo "\n";

$sql = "select b.meta_description as description_short, a.id_product, b.link_rewrite, round(a.price*1.19, 2) as preis, "
	. " b.name, a.ean13, a.id_manufacturer, a.id_category_default "
	. " from ps_product a, ps_product_lang b "
	. " where a.active = '1' "
	. " and a.available_for_order = '1' "
	. " and a.id_product = b.id_product "
	. " and b.id_lang = '$lang_id'";
// $sql .= " and a.id_product = '45'";
$rows = $db->executeS($sql);

foreach ($rows as $data) {
	// get images
	$bildsql = "select id_image from ps_image where id_product = '" . $data['id_product'] . "' and position = '1'";
	$bildquery = $db->executeS($bildsql);
	$bild_id = $bildquery[0]['id_image'];

	// get manufacturer name
	$hssql = "select name from ps_manufacturer where id_manufacturer = '" . $data['id_manufacturer'] . "'";
	$hsquery = $db->ExecuteS($hssql);
	$hs_name = $hsquery[0]['name'];

	// get category name and link
	$katsql = "select link_rewrite, name from ps_category_lang where id_category = '" . $data['id_category_default'] . "' and id_lang = '$lang_id'";
	$kat_query = $db->ExecuteS($katsql);
	$kat_name = $kat_query[0]['name'];
	$kat_rewrite = $kat_query[0]['link_rewrite'];


	$csv_bezeichnung = str_replace("\"", "'", $data['name']);
	$csv_ean = $data['ean13'];

	$csv_beschreibung = str_replace("\"", "'", $data['description_short']);
	$csv_beschreibung = str_replace("?", "", $csv_beschreibung);
	$csv_beschreibung = str_replace("\r\n", " ", $csv_beschreibung);
	$csv_beschreibung = str_replace("</p>", "</p> ", $csv_beschreibung);
	// $csv_beschreibung = "  ";

	$csv_preis = $data['preis'];
	$csv_waehrung = "EUR";

	$csv_zustand = "neu";

	$csv_versandkosten = "DE::0.00 EUR";

	// get color and size
	$att = "select a.id_product_attribute, a.ean13, b.id_attribute, d.`name`, f.id_attribute_group "
		. " from ps_product_attribute a, ps_product_attribute_combination b, ps_attribute c, ps_attribute_lang d, "
		. " ps_attribute_group e, ps_attribute_group_lang f "
		. " where a.`id_product_attribute` = b.`id_product_attribute` "
		. " and b.`id_attribute` = c.`id_attribute` "
		. " and b.`id_attribute` = d.`id_attribute` "
		. " and c.`id_attribute_group` = e.`id_attribute_group` "
		. " and e.`id_attribute_group` = f.`id_attribute_group` "
		. " and a.id_product = " . $data['id_product'] . " "
		. " order by a.`id_product_attribute`";
	$att_q = $db->ExecuteS($att);

	$attribute = array();
	foreach ($att_q as $myatt) {
		$attribute[$data['id_product']][$myatt['id_product_attribute']][$myatt['id_attribute_group']] = $myatt['name'];
		$attribute[$data['id_product']][$myatt['id_product_attribute']]['name'] = $myatt['id_product_attribute'];
	}

	foreach ($attribute[$data['id_product']] as $attribute_data) {
		$color = $attribute_data['1'];
		$size = $attribute_data['2'];

		$rabatt_price = 0;

		// special product price
		$_sql_price = "select round(reduction, 2) as rabatt, reduction_type from ps_specific_price "
			. " where id_product = '" . $data['id_product'] . "' "
			. " and id_specific_price_rule = '0' "
			. " and `id_product_attribute` = '" . $attribute_data['name'] . "'";
		$_price_q = $db->ExecuteS($_sql_price);
		if (count($_price_q) > 0) {
			// Es gibt einen Sonderpreis
			if ($_price_q[0]['reduction_type'] == 'percentage') // prozentualer rabatt
			{
				$rabatt = 1 - $_price_q[0]['rabatt'];
				$rabatt_price = round($csv_preis * $rabatt, 2);
			} else {
				$rabatt = $_price_q[0]['rabatt'];
				$rabatt_price = round($csv_preis - $rabatt, 2);
			}
		} else {
			$_sql_price = "select round(reduction*1.16, 2) as rabatt from ps_specific_price "
				. " where id_product = '" . $data['id_product'] . "' "
				. " and id_specific_price_rule = '0' "
				. " and `id_product_attribute` = '0'";
			$_price_q = $db->ExecuteS($_sql_price);
			if (count($_price_q) > 0) {
				$rabatt = $_price_q[0]['rabatt'];
				$rabatt_price = $csv_preis - $rabatt;
			}
		}

		// Allgemeine Sonderpreise holen, sofern es keinen Produkt Rabatt gibt
		if ($rabatt_price == 0) {
			$_sql_price = "select reduction, reduction_type from ps_specific_price "
				. " where id_product = '0' and `from` < now() and `to` > now()";
			$_price_q = $db->ExecuteS($_sql_price);

			if ($_price_q[0]['reduction_type'] == "percentage") {
				$_rabatt = (100 - $_price_q[0]['reduction'] * 100) / 100;
				$rabatt_price = round($csv_preis * $_rabatt, 2);
			}
		}

		/*
							  if(count($_price_q) > 0 && $rabatt_price == 0) // es gibt einen rabatt für alle Produkte
							  {
								  if($_price_q[0]['reduction_type'] == 'percentage')
								  {
									  $rabatt = $_price_q[0]['reduction'];
									  $rabatt_price = $csv_preis * ((100 - ($rabatt*100))/100);
								  }
							  }
						  */


		$csv_id = $data['id_product'] . "-" . $attribute_data['name'] . "$id_zusatz";
		$csv_link = "$url" . $kat_rewrite . "/" . $data['link_rewrite'] . ":" . $data['id_product'] . "-" . $attribute_data['name'] . ".html/#";
		$csv_bild_url = "$url" . $bild_id . "-large_default/" . $data['link_rewrite'] . ".jpg";

		$produkt = "\"$csv_id\"$trenner";
		$produkt .= "\"$csv_bezeichnung\"$trenner";
		$produkt .= "\"$csv_ean\"$trenner";
		$produkt .= "\"" . strip_tags($csv_beschreibung) . "\"$trenner";
		$produkt .= "\"$csv_link\"$trenner";
		/* old
									  if($rabatt_price > 0)
									  {
										  $produkt .= "\"$rabatt_price $csv_waehrung\"$trenner";
									  } else {
										  $produkt .= "\"$csv_preis $csv_waehrung\"$trenner";
									  }
									  */
		// new
		if ($rabatt_price == 0) {
			$rabatt_price = $csv_preis;
		}
		$produkt .= "\"$csv_preis $csv_waehrung\"$trenner";
		$produkt .= "\"$rabatt_price $csv_waehrung\"$trenner";

		// $produkt .= "\"$csv_waehrung\"$trenner";
		$produkt .= "\"$csv_zustand\"$trenner";
		$produkt .= "\"$csv_bild_url\"$trenner";
		$produkt .= "\"" . $hs_name . "\"$trenner";
		$produkt .= "\"auf Lager\"$trenner";
		$produkt .= "\"$google_cat\"$trenner";
		$produkt .= "\":::\"$trenner";
		$produkt .= "\"$color\"$trenner";
		$produkt .= "\"$size\"";

		echo "$produkt\n";
	}
}
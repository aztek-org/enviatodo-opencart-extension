<?php
namespace Opencart\System\Library\Enviatodo;
/**
 * Class AddressMapper
 *
 * Translates OpenCart-shaped addresses (country_id, zone_id, postcode,
 * address_1, …) and the local origin row into the structure expected by
 * enviatodo.com /Api/rates_client (origin / destination blocks).
 *
 * @package Opencart\System\Library\Enviatodo
 */
class AddressMapper {
	/**
	 * @param array<string, mixed> $origin Row from oc_enviatodo_origin.
	 *
	 * @return array<string, string>
	 */
	public static function fromOrigin(array $origin): array {
		$phone = preg_replace('/\D+/', '', (string)($origin['phone'] ?? '')) ?? '';

		return [
			'address_type_id' => '1',
			'full_name'       => trim((string)($origin['contact'] ?? $origin['name'] ?? '')),
			'name'            => trim((string)($origin['contact'] ?? $origin['name'] ?? '')),
			'company'         => trim((string)($origin['name'] ?? '')),
			'email'           => (string)($origin['email'] ?? ''),
			'telephone'       => $phone,
			'street'          => (string)($origin['street'] ?? ''),
			'ext_number'      => trim((string)($origin['number'] ?? '')) !== '' ? trim((string)$origin['number']) : 'S/N',
			'int_number'      => '',
			'zip_code'        => (string)($origin['postal_code'] ?? ''),
			'suburb'          => trim((string)($origin['district'] ?? '')) !== '' ? trim((string)$origin['district']) : 'N/A',
			'municipality'    => (string)($origin['city']     ?? ''),
			'town'            => (string)($origin['city']     ?? ''),
			'state'           => (string)($origin['state']    ?? ''),
			'state_code'      => (string)($origin['state_code'] ?? ''),
			'country_code'    => strtoupper((string)($origin['country'] ?? 'MX')),
			'reference'       => '-',
			'default_addr'    => '1',
			'status_id'       => '0',
			'lat'             => '',
			'lng'             => '',
		];
	}

	/**
	 * Build the destination block from the address OpenCart hands the
	 * shipping model + zone/country lookups (so we can fill state_code
	 * and ISO-2 country code).
	 *
	 * @param array<string, mixed> $address OpenCart address (country_id, zone_id, postcode, address_1, address_2, firstname, …)
	 * @param array<string, mixed> $country Row from oc_country (iso_code_2, name, …) or [].
	 * @param array<string, mixed> $zone    Row from oc_zone joined with oc_zone_description (name, code, …) or [].
	 *
	 * @return array<string, string>
	 */
	public static function fromCheckout(array $address, array $country, array $zone): array {
		$full_name = trim((string)($address['firstname'] ?? '') . ' ' . (string)($address['lastname'] ?? ''));
		$street    = trim((string)($address['address_1'] ?? ''));
		$int       = trim((string)($address['address_2'] ?? ''));
		$phone     = preg_replace('/\D+/', '', (string)($address['telephone'] ?? '')) ?? '';
		$email     = trim((string)($address['email'] ?? ''));
		$city      = trim((string)($address['city'] ?? ''));

		return [
			'address_type_id' => '2',
			'full_name'       => $full_name !== '' ? $full_name : 'Cliente',
			'name'            => trim((string)($address['firstname'] ?? '')) ?: 'Cliente',
			'company'         => trim((string)($address['company'] ?? '')),
			'email'           => $email !== '' ? $email : 'quote@example.com',
			'telephone'       => $phone !== '' ? $phone : '0000000000',
			'street'          => $street !== '' ? $street : '-',
			'ext_number'      => 'S/N',
			'int_number'      => $int,
			'zip_code'        => (string)($address['postcode'] ?? ''),
			'suburb'          => trim((string)($address['address_2'] ?? '')) !== '' ? trim((string)$address['address_2']) : 'N/A',
			'municipality'    => $city !== '' ? $city : 'N/A',
			'town'            => $city !== '' ? $city : 'N/A',
			'state'           => (string)($zone['name'] ?? ''),
			'state_code'      => (string)($zone['code'] ?? ''),
			'country_code'    => strtoupper((string)($country['iso_code_2'] ?? 'MX')),
			'reference'       => '-',
			'default_addr'    => '1',
			'lat'             => '',
			'lng'             => '',
		];
	}
}

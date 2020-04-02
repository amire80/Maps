<?php

declare( strict_types = 1 );

namespace Maps;

use Html;
use ParamProcessor\ParameterTypes;
use ParamProcessor\ProcessingResult;

/**
 * @licence GNU GPL v2+
 */
class LeafletService implements MappingService {

	private $addedDependencies = [];

	public function getName(): string {
		return 'leaflet';
	}

	public function getAliases(): array {
		return [ 'leafletmaps', 'leaflet' ]; // TODO: main name should not be in here?
	}

	public function getParameterInfo(): array {
		$params = MapsFunctions::getCommonParameters();

		$params['zoom'] = [
			'type' => ParameterTypes::INTEGER,
			'range' => [ 0, 20 ],
			'default' => false,
			'message' => 'maps-par-zoom'
		];

		$params['defzoom'] = [
			'type' => ParameterTypes::INTEGER,
			'range' => [ 0, 20 ],
			'default' => self::getDefaultZoom(),
			'message' => 'maps-leaflet-par-defzoom'
		];

		$params['layers'] = [
			'aliases' => 'layer',
			'type' => 'string',
			'values' => array_keys( $GLOBALS['egMapsLeafletAvailableLayers'], true, true ),
			'default' => $GLOBALS['egMapsLeafletLayers'],
			'message' => 'maps-leaflet-par-layers',
			'islist' => true,
		];

		$params['overlays'] = [
			'aliases' => [ 'overlaylayers' ],
			'type' => ParameterTypes::STRING,
			'values' => array_keys( $GLOBALS['egMapsLeafletAvailableOverlayLayers'], true, true ),
			'default' => $GLOBALS['egMapsLeafletOverlayLayers'],
			'message' => 'maps-leaflet-par-overlaylayers',
			'islist' => true,
		];

		$params['resizable'] = [
			'type' => ParameterTypes::BOOLEAN,
			'default' => $GLOBALS['egMapsResizableByDefault'],
			'message' => 'maps-par-resizable'
		];

		$params['fullscreen'] = [
			'aliases' => [ 'enablefullscreen' ],
			'type' => ParameterTypes::BOOLEAN,
			'default' => false,
			'message' => 'maps-par-enable-fullscreen',
		];

		$params['scrollwheelzoom'] = [
			'aliases' => [ 'scrollzoom' ],
			'type' => ParameterTypes::BOOLEAN,
			'default' => true,
			'message' => 'maps-par-scrollwheelzoom',
		];

		$params['cluster'] = [
			'aliases' => [ 'markercluster' ],
			'type' => ParameterTypes::BOOLEAN,
			'default' => false,
			'message' => 'maps-par-markercluster',
		];

		$params['clustermaxzoom'] = [
			'type' => ParameterTypes::INTEGER,
			'default' => 20,
			'message' => 'maps-par-clustermaxzoom',
		];

		$params['clusterzoomonclick'] = [
			'type' => ParameterTypes::BOOLEAN,
			'default' => true,
			'message' => 'maps-par-clusterzoomonclick',
		];

		$params['clustermaxradius'] = [
			'type' => ParameterTypes::INTEGER,
			'default' => 80,
			'message' => 'maps-par-maxclusterradius',
		];

		$params['clusterspiderfy'] = [
			'type' => ParameterTypes::BOOLEAN,
			'default' => true,
			'message' => 'maps-leaflet-par-clusterspiderfy',
		];

		$params['geojson'] = [
			'type' => ParameterTypes::STRING,
			'default' => '',
			'message' => 'maps-displaymap-par-geojson',
		];

		$params['clicktarget'] = [
			'type' => ParameterTypes::STRING,
			'default' => '',
			'message' => 'maps-leaflet-par-clicktarget',
		];

		return $params;
	}

	/**
	 * @since 3.0
	 */
	public function getDefaultZoom() {
		return $GLOBALS['egMapsLeafletZoom'];
	}

	public function newMapId(): string {
		static $mapsOnThisPage = 0;

		$mapsOnThisPage++;

		return 'map_leaflet_' . $mapsOnThisPage;
	}

	public function getResourceModules( array $params ): array {
		$modules = [];

		$modules[] = 'ext.maps.leaflet.loader';

		if ( $params['resizable'] ) {
			$modules[] = 'ext.maps.resizable';
		}

		if ( $params['cluster'] ) {
			$modules[] = 'ext.maps.leaflet.markercluster';
		}

		if ( $params['fullscreen'] ) {
			$modules[] = 'ext.maps.leaflet.fullscreen';
		}

		if ( array_key_exists( 'geojson', $params ) ) {
			$modules[] = 'ext.maps.leaflet.editor';
		}

		if ( array_key_exists( 'ajaxquery', $params ) && $params['ajaxquery'] !== '' ) {
			$modules[] = 'ext.maps.leaflet.leafletajax';
		}

		return $modules;
	}

	public function getDependencyHtml( array $params ): string {
		$dependencies = [];

		// Only add dependencies that have not yet been added.
		foreach ( $this->getDependencies( $params ) as $dependency ) {
			if ( !in_array( $dependency, $this->addedDependencies ) ) {
				$dependencies[] = $dependency;
				$this->addedDependencies[] = $dependency;
			}
		}

		// If there are dependencies, put them all together in a string, otherwise return false.
		return $dependencies !== [] ? implode( '', $dependencies ) : false;
	}

	private function getDependencies( array $params ): array {
		$leafletPath = $GLOBALS['wgScriptPath'] . '/extensions/Maps/resources/lib/leaflet';

		return array_merge(
			[
				Html::linkedStyle( "$leafletPath/leaflet.css" ),
			],
			$this->getLayerDependencies( $params )
		);
	}

	private function getLayerDependencies( array $params ) {
		global $egMapsLeafletLayerDependencies, $egMapsLeafletAvailableLayers,
			   $egMapsLeafletLayersApiKeys;

		$layerDependencies = [];

		foreach ( $params['layers'] as $layerName ) {
			if ( array_key_exists( $layerName, $egMapsLeafletAvailableLayers )
				&& $egMapsLeafletAvailableLayers[$layerName]
				&& array_key_exists( $layerName, $egMapsLeafletLayersApiKeys )
				&& array_key_exists( $layerName, $egMapsLeafletLayerDependencies ) ) {
				$layerDependencies[] = '<script src="' . $egMapsLeafletLayerDependencies[$layerName] .
					$egMapsLeafletLayersApiKeys[$layerName] . '"></script>';
			}
		}

		return array_unique( $layerDependencies );
	}

	public function processingResultToMapParams( ProcessingResult $processingResult ): array {
		return $this->processedParamsToMapParams( $processingResult->getParameterArray() );
	}

	public function processedParamsToMapParams( array $params ): array {
		if ( $params['geojson'] !== '' ) {
			$fetcher = MapsFactory::globalInstance()->newGeoJsonFetcher();

			$result = $fetcher->fetch( $params['geojson'] );

			$params['geojson'] = $result->getContent();
			$params['GeoJsonSource'] = $result->getTitleValue() === null ? null : $result->getTitleValue()->getText();
			$params['GeoJsonRevisionId'] = $result->getRevisionId();
		}

		return $params;
	}

}

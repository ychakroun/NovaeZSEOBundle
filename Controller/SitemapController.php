<?php
/**
 * NovaeZSEOBundle SitemapController
 *
 * @package   Novactive\Bundle\eZSEOBundle
 * @author    Novactive <novaseobundle@novactive.com>
 * @copyright 2015 Novactive
 * @license   https://github.com/Novactive/NovaeZSEOBundle/blob/master/LICENSE MIT Licence
 */

namespace Novactive\Bundle\eZSEOBundle\Controller;

use eZ\Bundle\EzPublishCoreBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\Response;
use DOMDocument;
use eZ\Publish\API\Repository\LocationService;
use eZ\Publish\API\Repository\Values\Content\Location;
use eZ\Publish\API\Repository\Values\Content\LocationList;

/**
 * Class SitemapController
 */
class SitemapController extends Controller
{

    /**
     * Sitemaps.xml route
     *
     * @Route("/sitemap.xml")
     * @Method("GET")
     *
     * @return Response
     */
    public function sitemapAction()
    {
        $locationService = $this->get( "ezpublish.api.repository" )->getLocationService();
        $routerService   = $this->get( "ezpublish.urlalias_router" );
        $contentTypeService = $this->get( "ezpublish.api.repository" )->getContentTypeService();
        $rootLocationId  = $this->getConfigResolver()->getParameter( 'content.tree_root.location_id' );
        $excludes  = $this->getConfigResolver()->getParameter( 'sitemap_excludes', 'novae_zseo' );

        foreach ( $excludes['contentTypeIdentifiers'] as &$contentTypeIdentifier )
        {
            $contentTypeIdentifier = (int)$contentTypeService->loadContentTypeByIdentifier( $contentTypeIdentifier )->id;
        }

        $sitemap               = new DOMDocument( "1.0", "UTF-8" );
        $root                  = $sitemap->createElement( "urlset" );
        $sitemap->formatOutput = true;
        $root->setAttribute( "xmlns", "http://www.sitemaps.org/schemas/sitemap/0.9" );
        $sitemap->appendChild( $root );
        $loadChildrenFunc = function ( $parentLocationId ) use (
            &$loadChildrenFunc,
            $routerService,
            $locationService,
            $sitemap,
            $root,
            $excludes
        )
        {
            /** @var LocationService $locationService */
            /** @var Location $location */
            $location = $locationService->loadLocation( $parentLocationId );

            if ( ( !in_array( $location->id, $excludes['locations'] ) ) &&
                 ( !in_array( $location->id, $excludes['subtrees'] ) ) &&
                 ( !in_array( $location->contentInfo->contentTypeId, $excludes['contentTypeIdentifiers'] ) )
            )
            {
                $url = $routerService->generate( $location, [], true );
                $modified = date( "c", $location->contentInfo->modificationDate->getTimestamp() );
                $loc      = $sitemap->createElement( "loc", $url );
                $lastmod  = $sitemap->createElement( "lastmod", $modified );
                $urlElt   = $sitemap->createElement( "url" );
                $urlElt->appendChild( $loc );
                $urlElt->appendChild( $lastmod );
                $root->appendChild( $urlElt );
            }

            if ( !in_array( $location->id, $excludes['subtrees'] ) )
            {
                $childrenList = $locationService->loadLocationChildren( $location );
                /** @var LocationList $childrenList */
                if ( count( $childrenList->totalCount > 0 ) )
                {
                    foreach ( $childrenList->locations as $locationChild )
                    {
                        /** @var Location $locationChild */
                        $loadChildrenFunc( $locationChild->id );
                    }
                }
            }
        };

        $loadChildrenFunc( $rootLocationId );

        $response = new Response();
        $response->setSharedMaxAge( 24 * 3600 );
        $response->headers->set( "Content-Type", "text/xml" );
        $response->setContent( $sitemap->saveXML() );

        return $response;
    }
}

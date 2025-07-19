// External Dependencies.
import React, { useEffect, useState, useReducer, useRef } from 'react';
import { sortBy } from 'underscore';
import {
	SiteType,
	SiteOrder,
	SiteBusinessType,
	NoResultFound,
} from '@brainstormforce/starter-templates-components';
import { useNavigate } from 'react-router-dom';
import { decodeEntities } from '@wordpress/html-entities';
import { sprintf, __ } from '@wordpress/i18n';

// Internal Dependencies.
import { DefaultStep, PreviousStepLink, Button } from '../../components/index';
import './style.scss';
import { useStateValue } from '../../store/store';
import {
	isPro,
	whiteLabelEnabled,
	storeCurrentState,
	getAllSites,
} from '../../utils/functions';
import { setURLParmsValue } from '../../utils/url-params';
import SiteListSkeleton from './site-list-skeleton';
import GridSkeleton from './grid-skeleton';
import SiteGrid from './sites-grid/index';
import SiteSearch from './search-filter';
import FavoriteSites from './favorite-sites';
import RelatedSites from './related-sites';
import { ChevronUpIcon } from '@heroicons/react/24/outline';
import {
	SyncAndGetAllCategories,
	SyncAndGetAllCategoriesAndTags,
	isSyncUptoDate,
	fetchSitesPageCount,
	fetchPagedSites,
} from './header/sync-library/utils';

export const useFilteredSites = () => {
	const [ { builder, siteType, siteOrder, allSitesData } ] = useStateValue();
	let allSites =
		allSitesData && !! Object.keys( allSitesData ).length
			? allSitesData
			: getAllSites();
	let sites = [];

	// Fallback array check for Chrome browser.
	if ( allSitesData && Array.isArray( allSites ) ) {
		allSites = allSitesData.reduce( ( acc, site ) => {
			if ( site.id ) {
				acc[ `id-${ site.id }` ] = site;
			}
			return acc;
		}, {} );
	}

	if ( builder ) {
		for ( const siteId in allSites ) {
			if ( builder === allSites[ siteId ][ 'astra-site-page-builder' ] ) {
				sites[ siteId ] = allSites[ siteId ];
			}
		}
	}

	if ( siteType ) {
		for ( const siteId in sites ) {
			const currentSiteType =
				sites[ siteId ]?.[ 'astra-sites-type' ] || '';

			switch ( siteType ) {
				case 'signature':
					if ( currentSiteType !== siteType ) {
						delete sites[ siteId ];
					}
					break;
				case 'agency-mini':
					if ( 'agency-mini' !== currentSiteType ) {
						delete sites[ siteId ];
					}
					break;
				default:
					break;
			}
		}
	}

	if ( 'latest' === siteOrder && Object.keys( sites ).length ) {
		sites = sortBy( Object.values( sites ), 'publish-date' ).reverse();
	}

	return sites;
};

const SiteList = () => {
	const [ loadingSkeleton, setLoadingSkeleton ] = useState( true );
	const backToTopBtn = useRef( null );
	const allFilteredSites = useFilteredSites();
	const history = useNavigate();
	const [ siteData, setSiteData ] = useReducer(
		( state, newState ) => ( { ...state, ...newState } ),
		{
			gridSkeleton: false,
			sites: {},
		}
	);
	const [ storedState, dispatch ] = useStateValue();
	const {
		onMyFavorite,
		builder,
		siteSearchTerm,
		siteType,
		siteOrder,
		siteBusinessType,
		selectedMegaMenu,
		allSitesData,
		bgSyncInProgress,
	} = storedState;

	useEffect( () => {
		const loadingTimeout = setTimeout( () => {
			setLoadingSkeleton( false );
		}, 800 );

		return () => clearTimeout( loadingTimeout );
	}, [] );

	useEffect( () => {
		dispatch( {
			type: 'set',
			templateResponse: null,
			selectedTemplateName: '',
			selectedTemplateType: '',
			shownRequirementOnce: false,
			templateId: 0,
		} );

		setSiteData( {
			sites: allFilteredSites,
		} );
	}, [ builder, siteType, siteOrder, allSitesData ] );

	storeCurrentState( storedState );

	const siteCount = Object.keys( siteData.sites ).length;

	const backStep = () => {
		dispatch( {
			type: 'set',
			siteSearchTerm: '', // Reset the search term on back
			siteBusinessType: '', // Reset the business type on back
			currentIndex: builder === 'fse' ? 0 : 1,
		} );
		const urlParam = setURLParmsValue( 's' );
		history( `?${ urlParam }` );
	};

	const handleClickBackToTop = () => {
		const contentWrapper = document.querySelector( '.step-content ' );
		if ( ! contentWrapper ) {
			return;
		}
		contentWrapper.scrollTo( {
			top: 0,
			behavior: 'smooth',
		} );
	};

	useEffect( () => {
		// use timeout function so the new content wrapper for the builder will be loaded
		const scrollTopTimeout = setTimeout( () => {
			handleClickBackToTop();
		}, 300 );

		// Cleanup function to clear the timeout if the component unmounts
		return () => clearTimeout( scrollTopTimeout );
	}, [ builder ] );

	const handleShowBackToTop = ( event ) => {
		const SCROLL_THRESHOLD = 250;
		const target = event.target;

		if ( ! backToTopBtn.current ) {
			return;
		}

		const btn = backToTopBtn.current;
		if (
			target.scrollTop > SCROLL_THRESHOLD &&
			btn.classList.contains( 'hidden' )
		) {
			btn.classList.remove( 'hidden' );
		} else if (
			target.scrollTop <= SCROLL_THRESHOLD &&
			! btn.classList.contains( 'hidden' )
		) {
			btn.classList.add( 'hidden' );
		}
	};

	// const fetchSitesAndCategories = async () => {
	// 	try {
	// 		const formData = new FormData();
	// 		formData.append( 'action', 'astra-sites-update-library' );
	// 		formData.append( '_ajax_nonce', astraSitesVars?._ajax_nonce );
	// 		const response = await fetch( ajaxurl, {
	// 			method: 'post',
	// 			body: formData,
	// 		} );
	// 		const jsonData = await response.json();
	// 		if (
	// 			jsonData.data === 'updated' &&
	// 			Object.keys( storedState.allSitesData ).length !== 0
	// 		) {
	// 			dispatch( {
	// 				type: 'set',
	// 				bgSyncInProgress: false,
	// 			} );
	// 			return;
	// 		}

	// 		const sites = await SyncImportAllSites();
	// 		const categories = await SyncAndGetAllCategories();
	// 		const categoriesAndTags = await SyncAndGetAllCategoriesAndTags();
	// 		console.log( typeof dispatch );
	// 		dispatch( {
	// 			type: 'set',
	// 			bgSyncInProgress: false,
	// 			allSitesData: sites,
	// 			categories,
	// 			categoriesAndTags,
	// 		} );

	// 		// await fetchSitesAndCategories();
	// 	} catch ( error ) {
	// 		console.error( error );
	// 	}
	// };

	const syncSites = async () => {
		// const newData = await SyncStart();
		const pageCount = await fetchSitesPageCount();

		dispatch( {
			type: 'set',
			syncPageCount: pageCount,
		} );

		const sites = [];
		for ( let i = 0; i < pageCount; i++ ) {
			const sitesData = await fetchPagedSites( i + 1 );
			sitesData.forEach( ( siteItem ) => {
				sites.push( siteItem );
			} );
			dispatch( {
				type: 'set',
				syncPageInProgress: i + 1,
			} );
		}

		if ( sites.length > 0 ) {
			return sites;
		}
		return null;
	};

	const fetchSitesAndCategories = async () => {
		try {
			const syncUptoDate = await isSyncUptoDate();

			dispatch( {
				type: 'set',
				syncPageInProgress: 0,
				syncPageCount: 0,
			} );

			if ( syncUptoDate ) {
				dispatch( {
					type: 'set',
					bgSyncInProgress: false,
				} );
				return;
			}

			const sites = await syncSites();
			const categories = await SyncAndGetAllCategories();
			const categoriesAndTags = await SyncAndGetAllCategoriesAndTags();

			const updatedState = {
				type: 'set',
				bgSyncInProgress: false,
				syncPageInProgress: 0,
				syncPageCount: 0,
			};

			if ( ! sites || ! categories || ! categoriesAndTags ) {
				updatedState.allSitesData = sites ?? null;
				updatedState.categories = categories ?? null;
				updatedState.categoriesAndTags = categoriesAndTags ?? null;
			} else {
				updatedState.allSitesData = sites;
				updatedState.categories = categories;
				updatedState.categoriesAndTags = categories;
			}
			dispatch( updatedState );
			astraSitesVars.bgSyncInProgress = false;

			// await fetchSitesAndCategories();
		} catch ( error ) {
			console.error( error );
		}
	};

	useEffect( () => {
		// if ( ! bgSyncInProgress ) {
		// 	return;
		// }

		fetchSitesAndCategories();
	}, [] );

	useEffect( () => {
		const contentWrapper = document.querySelector( '.step-content ' );
		if ( ! contentWrapper ) {
			return;
		}
		contentWrapper.addEventListener( 'scroll', handleShowBackToTop );
		return () => {
			contentWrapper.removeEventListener( 'scroll', handleShowBackToTop );
		};
	}, [] );

	const showSkeleton =
		siteData.gridSkeleton || bgSyncInProgress || loadingSkeleton;

	return (
		<DefaultStep
			content={
				<>
					<div
						className={ `site-list-screen-container ${
							loadingSkeleton ? 'site-loading' : 'site-loaded'
						}` }
					>
						<SiteListSkeleton />
						<div className="site-list-screen-wrap flex flex-col gap-6 mx-auto">
							<div>
								<h3 className="site-list-title">
									{ __(
										'What type of website are you building?',
										'astra-sites'
									) }
								</h3>
							</div>

							<div className="site-list-content">
								<SiteSearch setSiteData={ setSiteData } />

								<div className="st-templates-content">
									<div className="st-other-filters">
										<div className="st-category-filter">
											<SiteBusinessType
												parent={ siteBusinessType }
												menu={ selectedMegaMenu }
												onClick={ (
													event,
													option,
													childItem
												) => {
													dispatch( {
														type: 'set',
														siteBusinessType:
															option.ID,
														selectedMegaMenu:
															childItem.ID,
														siteSearchTerm:
															childItem.title,
														onMyFavorite: false,
														siteOrder: 'popular',
													} );
													const urlParam =
														setURLParmsValue(
															's',
															childItem.title
														);
													history( `?${ urlParam }` );
												} }
											/>
										</div>
										<div className="st-type-and-order-filters">
											<SiteType
												value={ siteType }
												onClick={ ( event, type ) => {
													dispatch( {
														type: 'set',
														siteType: type.id,
														onMyFavorite: false,
													} );
												} }
											/>

											<SiteOrder
												value={ siteOrder }
												onClick={ ( event, order ) => {
													dispatch( {
														type: 'set',
														siteOrder: order.id,
														onMyFavorite: false,
														siteBusinessType: '',
														selectedMegaMenu: '',
														siteSearchTerm: '',
													} );
													const urlParam =
														setURLParmsValue(
															's',
															''
														);
													history( `?${ urlParam }` );
												} }
											/>
										</div>
									</div>

									{ onMyFavorite ? (
										<FavoriteSites />
									) : (
										<>
											{ ( !! siteCount ||
												showSkeleton ) && (
												<>
													<div className="st-sites-grid">
														{ siteSearchTerm ? (
															<div className="st-sites-found-message">
																{ sprintf(
																	/* translators: %1$s: search term. */
																	__(
																		'Starter Templates for %1$s:',
																		'astra-sites'
																	),
																	decodeEntities(
																		siteSearchTerm
																	)
																) }
															</div>
														) : null }

														{ showSkeleton ? (
															<GridSkeleton />
														) : (
															<SiteGrid
																sites={
																	siteData.sites
																}
															/>
														) }
													</div>
												</>
											) }

											{ ! siteCount && ! showSkeleton && (
												<>
													<NoResultFound
														searchTerm={
															siteSearchTerm
														}
													/>
													<RelatedSites
														sites={
															allFilteredSites
														}
													/>
												</>
											) }
										</>
									) }
								</div>
							</div>
						</div>
						{ /* Back to the top */ }
						<div
							ref={ backToTopBtn }
							className="hidden absolute right-20 bottom-28 ml-auto"
						>
							<button
								type="button"
								className="absolute bottom-0 right-0 z-10 w-8 h-8 rounded-full bg-accent-st-secondary border-0 border-solid text-white flex items-center justify-center shadow-sm cursor-pointer"
								onClick={ handleClickBackToTop }
							>
								<ChevronUpIcon className="w-5 h-5" />
							</button>
						</div>
					</div>
				</>
			}
			actions={
				<div className="step-action-wrapper">
					<PreviousStepLink before onClick={ backStep }>
						{ __( 'Back', 'astra-sites' ) }
					</PreviousStepLink>

					{ ! isPro() && ! whiteLabelEnabled() && (
						<div className="cta-strip-right">
							<h5>
								{ __(
									'Get unlimited access to all Premium Starter Templates and more, at a single low cost!',
									'astra-sites'
								) }
							</h5>
							<Button
								className="st-access-btn"
								onClick={ () =>
									window.open(
										astraSitesVars?.cta_links[ builder ]
									)
								}
							>
								{ __( 'Get Essential Toolkit', 'astra-sites' ) }
							</Button>
						</div>
					) }
				</div>
			}
		/>
	);
};

export default SiteList;

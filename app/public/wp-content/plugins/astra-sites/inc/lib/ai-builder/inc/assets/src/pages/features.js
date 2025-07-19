import { useEffect, useState, useMemo } from '@wordpress/element';
import {
	FunnelIcon,
	HeartIcon,
	PlayCircleIcon,
	SquaresPlusIcon,
	CheckIcon,
	ChatBubbleLeftEllipsisIcon,
	WrenchIcon,
	PaintBrushIcon,
	Squares2X2Icon,
	QueueListIcon,
	ShoppingCartIcon,
	ChevronUpIcon,
	EnvelopeIcon,
	CalendarIcon,
	ArrowTrendingUpIcon,
} from '@heroicons/react/24/outline';
import { __ } from '@wordpress/i18n';
import { useDispatch, useSelect } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';
import { STORE_KEY } from '../store';
import { classNames } from '../helpers';
import NavigationButtons from '../components/navigation-buttons';
import { useNavigateSteps } from '../router';
import withBuildSiteController from '../hoc/withBuildSiteController';
import Container from '../components/container';
import Heading from '../components/heading';
import Dropdown from '../components/dropdown';
import AISitesNotice from '../components/ai-sites-notice';
import { WooCommerceIcon, SureCartIcon } from '../ui/icons';
import CreditConfirmModal from '../components/CreditConfirmModal';
import { getFeaturePluginList } from '../utils/import-site/import-utils';
import RequiredPlugins from '../components/RequiredPlugins';

const fetchStatus = {
	fetching: 'fetching',
	fetched: 'fetched',
	error: 'error',
};

const getPluginProps = ( id ) => {
	switch ( id ) {
		case 'surecart':
			return {
				title: 'SureCart',
				icon: <SureCartIcon className="w-3 h-3" />,
			};
		case 'woocommerce':
			return {
				title: 'WooCommerce',
				icon: <WooCommerceIcon className="w-3 h-3" />,
			};
		default:
			return {
				title: 'SureCart',
				icon: <SureCartIcon className="w-3 h-3" />,
			};
	}
};

const EcommerceOptions = ( { ecomSupported, selectedEcom, onChange } ) => {
	const { setSiteFeaturesData } = useDispatch( STORE_KEY );
	const [ open, setOpen ] = useState( false );

	const isOnlyOneEcom = ecomSupported.length === 1;
	const handleDropdownClick = ( event ) => {
		event.stopPropagation();
		setOpen( ! open );
	};
	const handleOptionClick = ( id, event ) => {
		event.stopPropagation();
		onChange( id );
		setOpen( false );
		setSiteFeaturesData( { ecommerce_type: id } );
	};
	return (
		<div className="bg-[#F6FAFE] z-50 py-1 px-2 shadow-sm rounded-md items-center justify-center w-fit">
			<Dropdown
				width="w-36"
				trigger={
					<div
						className={ classNames(
							'flex items-center  cursor-pointer gap-1.5',
							isOnlyOneEcom ? 'pointer-events-none' : ''
						) }
						onClick={ handleDropdownClick }
					>
						<div className="flex items-center ">
							{ getPluginProps( selectedEcom ).icon }
							<div className="ml-2">
								<p className="text-xs leading-3 text-app-text">
									{ getPluginProps( selectedEcom ).title }
								</p>
							</div>
						</div>
						<span>
							{ ! isOnlyOneEcom && ( // Hide the chevron if there is only one ecom option
								<ChevronUpIcon
									className={ classNames(
										'w-3 h-3 text-app-active-icon ',
										open ? 'transform rotate-180' : ''
									) }
								/>
							) }
						</span>
					</div>
				}
				onOpenChange={ setOpen }
			>
				<div className="py-0.5 px-2 mx-auto bg-white rounded-md">
					{ ecomSupported?.map( ( id, index ) => {
						const { icon, title } = getPluginProps( id );
						return (
							<Dropdown.Item
								key={ index }
								onClick={ ( event ) =>
									handleOptionClick( id, event )
								}
								className={ classNames(
									'flex items-center px-2 py-1 hover:bg-container-background rounded-md cursor-pointer'
								) }
							>
								<div className="flex items-center">
									{ icon }
									<div className="ml-2">
										<p className="text-xs leading-5 text-app-text">
											{ title }
										</p>
									</div>
								</div>
							</Dropdown.Item>
						);
					} ) }
				</div>
			</Dropdown>
		</div>
	);
};

const ICON_SET = {
	heart: HeartIcon,
	'squares-plus': SquaresPlusIcon,
	funnel: FunnelIcon,
	'play-circle': PlayCircleIcon,
	'live-chat': ChatBubbleLeftEllipsisIcon,
	'page-builder': PaintBrushIcon,
	'contact-form': QueueListIcon,
	blog: Squares2X2Icon,
	ecommerce: ShoppingCartIcon,
	envelope: EnvelopeIcon,
	calendar: CalendarIcon,
	'arrow-trending-up': ArrowTrendingUpIcon,
};

const Features = ( { handleClickStartBuilding, isInProgress } ) => {
	const { previousStep } = useNavigateSteps();
	const disabledFeatures = aiBuilderVars?.hide_site_features;
	const { setSiteFeatures, storeSiteFeatures } = useDispatch( STORE_KEY );
	const { setSignupLoginModal } = useDispatch( STORE_KEY );

	const authenticated = aiBuilderVars?.zip_token_exists;

	const { siteFeatures, loadingNextStep } = useSelect( ( select ) => {
		const { getSiteFeatures, getLoadingNextStep } = select( STORE_KEY );

		return {
			siteFeatures: getSiteFeatures(),
			loadingNextStep: getLoadingNextStep(),
		};
	}, [] );

	const {
		stepsData: {
			selectedTemplate,
			templateList,
			selectedTemplateIsPremium,
			pageBuilder,
		},
	} = useSelect( ( select ) => {
		const { getAIStepData } = select( STORE_KEY );

		return {
			stepsData: getAIStepData(),
		};
	}, [] );
	const selectedTemplateData = templateList.find(
		( item ) => item.uuid === selectedTemplate
	);

	// const enabledFeatures = siteFeatures
	// 	.filter( ( feature ) => feature.enabled )
	// 	.map( ( feature ) => feature.id );

	// const uniqueSiteFeatures = [ ...new Set( enabledFeatures ) ];
	// const ecommerceEnabled = uniqueSiteFeatures.includes( 'ecommerce' );
	const [ ecomSupported, defaultEcom ] = useMemo( () => {
		return [
			selectedTemplateData?.features_data?.ecommerce_supported || [],
			selectedTemplateData?.features_data?.ecommerce_type,
		];
	}, [] );
	const [ selectedEcom, setSelectedEcom ] = useState( defaultEcom );

	const [ isFetchingStatus, setIsFetchingStatus ] = useState(
		fetchStatus.fetching
	);

	const fetchSiteFeatures = async () => {
		const response = await apiFetch( {
			path: 'zipwp/v1/site-features',
			method: 'GET',
			headers: {
				'X-WP-Nonce': aiBuilderVars.rest_api_nonce,
			},
		} );

		if ( response?.success ) {
			// Store to state.
			storeSiteFeatures( response.data.data );

			// Set status to fetched.
			return setIsFetchingStatus( fetchStatus.fetched );
		}

		setIsFetchingStatus( fetchStatus.error );
	};

	const handleToggleFeature = ( feature ) => () => {
		if ( feature.compulsory && feature.enabled ) {
			return;
		}

		setSiteFeatures( feature.id );
		storeSiteFeatures(
			siteFeatures.map( ( f ) => {
				if ( f.id === feature.id ) {
					return {
						...f,
						enabled: ! f.enabled,
					};
				}
				return f;
			} )
		);
	};

	useEffect( () => {
		if ( siteFeatures?.length > 0 ) {
			// we already have features
			storeSiteFeatures( siteFeatures );
			setIsFetchingStatus( fetchStatus.fetched );
		} else if ( isFetchingStatus === fetchStatus.fetching ) {
			fetchSiteFeatures();
		}
	}, [] );

	const listOfFeatures = useMemo( () => {
		// Exclude disabled features from UI only when site features have been fetched.
		return isFetchingStatus === fetchStatus.fetched
			? siteFeatures?.filter(
					( feat ) => ! disabledFeatures?.includes( feat.id )
			  )
			: [];
	}, [ siteFeatures, disabledFeatures, isFetchingStatus ] );

	const handleClickNext = ( { skipFeature = false } ) => {
		if ( ! authenticated ) {
			setSignupLoginModal( {
				open: true,
				type: 'register',
				ask: 'register',
				shouldResume: true,
				isPremiumTemplate: selectedTemplateIsPremium,
			} );
			return;
		}

		// get the start building function from the parent component
		const startBuilding = handleClickStartBuilding( skipFeature );

		if ( aiBuilderVars?.hideCreditsWarningModal ) {
			startBuilding();
			return;
		}

		const isPlanEligibleForConfirmation = [ 'free', 'hobby' ].includes(
			aiBuilderVars?.zip_plans?.active_plan?.slug
		);

		const hasRemainingCredits =
			aiBuilderVars?.zip_plans?.plan_data?.remaining?.ai_sites_count > 0;

		if ( isPlanEligibleForConfirmation && hasRemainingCredits ) {
			CreditConfirmModal.show( {
				onConfirm: startBuilding,
			} );
		} else {
			// user doesn't have sufficient credits or confirmation modal is not needed, startBuilding will show upgrade modal if needed
			startBuilding();
		}
	};

	const featurePluginsList = useMemo( () => {
		const enabledFeatureIds =
			siteFeatures
				?.filter( ( feature ) => feature.enabled )
				.map( ( feature ) => feature.id ) ?? [];

		const builderPlugin = {
			name: pageBuilder === 'elementor' ? 'Elementor' : 'Spectra',
			slug:
				pageBuilder === 'elementor'
					? 'elementor'
					: 'ultimate-addons-for-gutenberg',
			compulsory: true,
		};

		const formPlugin = {
			name: 'SureForms',
			slug: 'sureforms',
			compulsory: siteFeatures?.find(
				( feature ) => feature.id === 'contact-form'
			)?.compulsory,
		};

		return [
			builderPlugin,
			formPlugin,
			...( getFeaturePluginList(
				enabledFeatureIds,
				selectedEcom,
				siteFeatures
			) ?? [] ),
		];
	}, [ isFetchingStatus, siteFeatures, selectedEcom ] );

	return (
		<>
			<Container className="grid grid-cols-1 gap-[26px] auto-rows-auto !max-w-[55rem] w-full mx-auto">
				<AISitesNotice />
				<div className="space-y-4">
					<Heading
						heading={ __( 'Select features', 'ai-builder' ) }
						subHeading={ __(
							'Select the features you want on this website',
							'ai-builder'
						) }
						className="leading-9"
						subClassName="!mt-2"
					/>
				</div>
				{ /* Feature Cards */ }

				<div className="grid grid-cols-1 lg:grid-cols-2 auto-rows-auto gap-7 w-full">
					{ isFetchingStatus === fetchStatus.fetched &&
						listOfFeatures.map( ( feature ) => {
							const isEcommerce = feature.id === 'ecommerce';

							const FeatureIcon = ICON_SET?.[ feature.icon ];
							return (
								<div
									key={ feature.id }
									className={ classNames(
										'relative py-4 pl-4 pr-5 rounded-md shadow-sm border border-solid bg-white border-button-disabled transition-colors duration-150 ease-in-out',
										feature.enabled && 'border-accent-st',
										'cursor-pointer'
									) }
									data-disabled={ loadingNextStep }
									onClick={ handleToggleFeature( feature ) }
								>
									<div className="flex items-start justify-start gap-3">
										<div className="p-0.5 shrink-0">
											{ FeatureIcon && (
												<FeatureIcon className="text-zip-body-text w-7 h-7" />
											) }
											{ ! FeatureIcon && (
												<WrenchIcon className="text-zip-body-text w-7 h-7" />
											) }
										</div>
										<div className="space-y-1 mr-0 w-full">
											<p className="p-0 m-0 !text-base !font-semibold !text-zip-app-heading">
												{ feature.title }
											</p>
											<div className="flex justify-between items-start w-full">
												<p className="p-0 m-0 !text-sm !font-normal !text-zip-body-text">
													{ feature.description }
												</p>
												<div
													onClick={ ( e ) =>
														e.stopPropagation()
													}
												>
													{ isEcommerce && (
														<EcommerceOptions
															ecomSupported={
																ecomSupported
															}
															selectedEcom={
																selectedEcom
															}
															onChange={
																setSelectedEcom
															}
														/>
													) }
												</div>
											</div>
										</div>
									</div>
									{ /* Check mark */ }

									<span
										className={ classNames(
											'inline-flex absolute top-4 right-4 p-[0.15rem] border border-solid border-zip-app-inactive-icon rounded',
											feature.enabled &&
												'border-accent-st bg-accent-st',
											feature.compulsory &&
												'border-button-disabled bg-button-disabled'
										) }
									>
										<CheckIcon
											className="w-2.5 h-2.5 text-white"
											strokeWidth={ 4 }
										/>
									</span>
								</div>
							);
						} ) }
					{ /* Skeleton */ }
					{ isFetchingStatus === fetchStatus.fetching &&
						Array.from( {
							length: Object.keys( ICON_SET ).length,
						} ).map( ( _, index ) => (
							<div
								key={ index }
								className="relative py-4 pl-4 pr-5 rounded-md shadow-sm border border-solid bg-white border-button-disabled"
							>
								<div className="flex items-start justify-start gap-3">
									<div className="p-0.5 shrink-0">
										<div className="w-7 h-7 bg-gray-200 rounded animate-pulse" />
									</div>
									<div className="space-y-1 w-full">
										<div className="w-3/4 h-6 bg-gray-200 rounded animate-pulse" />
										<div className="w-1/2 h-5 bg-gray-200 rounded animate-pulse" />
									</div>
								</div>
								<span className="inline-flex absolute top-4 right-4 w-4 h-4 bg-gray-200 animate-pulse rounded" />
								<div className="absolute inset-0 cursor-pointer" />
							</div>
						) ) }
				</div>
				{ /* Error Message */ }
				{ isFetchingStatus === fetchStatus.error && (
					<div className="flex items-center justify-center w-full px-5 py-5">
						<p className="text-secondary-text text-center px-10 py-5 border-2 border-dashed border-border-primary rounded-md">
							{ __(
								'Something went wrong. Please try again later.',
								'ai-builder'
							) }
						</p>
					</div>
				) }

				<hr className="!border-border-tertiary border-b-0 w-full" />

				{ /* Navigation buttons */ }
				<NavigationButtons
					continueButtonText={ __( 'Start Building', 'ai-builder' ) }
					onClickPrevious={ previousStep }
					onClickContinue={ handleClickNext }
					onClickSkip={ () =>
						handleClickNext( { skipFeature: true } )
					}
					loading={ isInProgress }
					skipButtonText={ __(
						'Skip & Start Building',
						'ai-builder'
					) }
				/>
			</Container>

			{ isFetchingStatus === fetchStatus.fetched && (
				<RequiredPlugins pluginsList={ featurePluginsList } />
			) }
		</>
	);
};

export default withBuildSiteController( Features );

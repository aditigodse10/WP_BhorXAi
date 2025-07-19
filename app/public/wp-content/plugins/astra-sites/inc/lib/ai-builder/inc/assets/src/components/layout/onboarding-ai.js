import { Outlet } from '@tanstack/react-router';
import { CheckIcon } from '@heroicons/react/24/outline';

import {
	useState,
	memo,
	useEffect,
	useLayoutEffect,
	Fragment,
	useRef,
} from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { removeQueryArgs } from '@wordpress/url';

import {
	classNames,
	getLocalStorageItem,
	setLocalStorageItem,
	getScreenWidthBreakPoint,
} from '../../helpers/index';
import PreviewWebsite from '../../pages/preview';
import { STORE_KEY } from '../../store';
import LimitExceedModal from '../limit-exceeded-modal';
import ContinueProgressModal from '../continue-progress-modal';
import ConfirmationStartOverModal from '../confimation-start-over-modal';
import AiBuilderExitButton from '../ai-builder-exit-button';
import { AnimatePresence } from 'framer-motion';
import { useNavigateSteps, steps, useValidateStep } from '../../router';
import ToasterContainer from '../toast-container';
import ErrorBoundary from '../../pages/error-boundary';
import useEffectAfterMount from '../../hooks/use-effect-after-mount';
import ApiErrorModel from '../api-error-model';
import PlanInformationModal from '../plan-information-modal';
import PlanUpgradePromoModal from '../plan-upgrade-promo';
import SignupLoginModal from '../signup-login-modal';

const { logoUrlLight } = aiBuilderVars;

const OnboardingAI = () => {
	const {
		currentStepURL,
		currentStepIndex: currentStep,
		navigateTo,
	} = useNavigateSteps();
	const redirectToStepURL = useValidateStep( currentStepURL );

	const authenticated = aiBuilderVars?.zip_token_exists,
		isAuthScreen = currentStep === 0;

	const urlParams = new URLSearchParams( window.location.search );

	// catch the query params from the URL, we're using useRef to avoid
	// re-rendering the component when the URL changes
	const showContinueProgressModal = useRef(
		! urlParams.get( 'should_resume' )
	).current;

	const { setContinueProgressModal, setConfirmationStartOverModal } =
		useDispatch( STORE_KEY );
	const { continueProgressModal } = useSelect( ( select ) => {
		const { getContinueProgressModalInfo } = select( STORE_KEY );
		return {
			continueProgressModal: getContinueProgressModalInfo(),
		};
	}, [] );

	const aiOnboardingDetails = useSelect( ( select ) => {
		const { getOnboardingAI } = select( STORE_KEY );
		return getOnboardingAI();
	} );
	const selectedTemplate = aiOnboardingDetails?.stepData?.selectedTemplate,
		{ loadingNextStep } = aiOnboardingDetails;

	const [ initialRedirectDone, setInitialRedirectDone ] = useState( false );
	const [ breakPoint, setBreakPoint ] = useState(
		getScreenWidthBreakPoint()
	);

	useEffect( () => {
		if ( initialRedirectDone ) {
			return;
		}

		const savedData = getLocalStorageItem(
			'ai-builder-onboarding-details'
		);

		const shouldRedirectToLastStep =
			! urlParams.get( 'skip_redirect_last_step' ) &&
			savedData?.lastVisitedStep;

		if ( shouldRedirectToLastStep ) {
			navigateTo( {
				to: savedData.lastVisitedStep,
				replace: true,
			} );
			if ( showContinueProgressModal ) {
				setContinueProgressModal( { open: true } );
				setConfirmationStartOverModal( { open: false } );
			}
		} else if ( ! urlParams.get( 'skip_redirect_last_step' ) ) {
			navigateTo( {
				to: redirectToStepURL,
				replace: true,
			} );
		}
		setInitialRedirectDone( true );
	}, [ initialRedirectDone, redirectToStepURL ] );

	useEffectAfterMount( () => {
		if (
			! aiOnboardingDetails?.stepData?.businessType ||
			'' === aiOnboardingDetails?.stepData?.businessType
		) {
			return;
		}
		if ( ! continueProgressModal?.open ) {
			setLocalStorageItem( 'ai-builder-onboarding-details', {
				...aiOnboardingDetails,
				lastVisitedStep: currentStepURL,
			} );
		}
	}, [ aiOnboardingDetails, currentStepURL ] );

	useEffect( () => {
		const savedAiOnboardingDetails = getLocalStorageItem(
			'ai-builder-onboarding-details'
		);

		if (
			showContinueProgressModal &&
			savedAiOnboardingDetails?.stepData?.businessType &&
			authenticated
		) {
			setContinueProgressModal( {
				open: true,
			} );
			setConfirmationStartOverModal( { open: false } );
		}

		const handleResize = () => {
			setBreakPoint( getScreenWidthBreakPoint() );
		};
		window.addEventListener( 'resize', handleResize );
		return () => {
			window.removeEventListener( 'resize', handleResize );
		};
	}, [] );

	const dynamicStepClassNames = ( step, stepIndex ) => {
		if ( step === stepIndex ) {
			return 'border-accent-st bg-white text-accent-st border-solid';
		}
		if ( step > stepIndex ) {
			return 'bg-secondary-text text-white border-secondary-text border-solid';
		}
		if (
			getScreenWidthBreakPoint() === 'sm' ||
			getScreenWidthBreakPoint() === 'xs'
		) {
			return 'border-solid border-step-connector bg-inherit bg-step-connector';
		}
		return 'border-solid border-step-connector text-secondary-text';
	};

	const dynamicClass = function ( cStep, sIndex ) {
		if ( steps?.[ sIndex ].layoutConfig?.screen === 'done' ) {
			return '';
		}
		if ( cStep === sIndex ) {
			return 'bg-accent-st';
		}
		return 'bg-border-line-inactive';
	};

	useLayoutEffect( () => {
		const token = urlParams.get( 'token' );
		if ( token ) {
			const url = removeQueryArgs(
				window.location.href,
				'token',
				'email',
				'action',
				'credit_token',
				'security',
				'should_resume'
			);

			window.onbeforeunload = null;
			window.history.replaceState( {}, '', url + '#/' );
		}
	}, [ currentStep, currentStepURL, aiOnboardingDetails ] );

	const getStepIndex = ( value, by = 'path' ) => {
		return steps.findIndex( ( item ) => item[ by ] === value );
	};

	const moveToStep = ( stepURL, stepIndex ) => () => {
		if (
			currentStep === stepIndex ||
			currentStep > getStepIndex( '/features' ) ||
			currentStep < stepIndex ||
			loadingNextStep
		) {
			return;
		}

		navigateTo( {
			to: stepURL,
		} );
	};

	const { setPlanInformationModal } = useDispatch( STORE_KEY );

	const { planInformationModal } = useSelect( ( select ) => {
		const { getPlanInfoModalInfo } = select( STORE_KEY );
		return {
			planInformationModalInfo: getPlanInfoModalInfo(),
		};
	} );

	const {
		zip_plans: { active_plan },
		show_zip_plan,
	} = aiBuilderVars;

	const renderStepContent = ( stepIdx, currStep, stepNumber ) => {
		if ( currStep === stepIdx ) {
			return stepNumber;
		} else if ( currStep > stepIdx ) {
			return <CheckIcon className="max-sm:hidden h-3 w-3" />;
		} else if (
			getScreenWidthBreakPoint() === 'sm' ||
			getScreenWidthBreakPoint() === 'xs'
		) {
			return '';
		}
		return stepNumber;
	};

	return (
		<>
			<div
				id="spectra-onboarding-ai"
				className={ classNames(
					'font-figtree h-screen grid grid-cols-1 shadow-medium grid-rows-[4rem_1fr]',
					isAuthScreen && 'grid-rows-1'
				) }
			>
				{ ! isAuthScreen && (
					<header
						className={ classNames(
							'w-full h-full grid grid-cols-[5rem_1fr_8rem] sm:grid-cols-[6.75rem_1fr_8rem] items-center justify-between md:justify-start z-[5] relative bg-white shadow pl-3 sm:pl-5',
							steps[ currentStep ]?.layoutConfig?.hideHeader &&
								'justify-center md:justify-between'
						) }
					>
						{ /* Brand logo */ }
						<img
							className="max-h-10"
							src={ logoUrlLight }
							alt={ __( 'Build with AI', 'ai-builder' ) }
						/>

						{ /* Steps/Navigation items */ }
						{ ! steps[ currentStep ]?.layoutConfig?.hideHeader && (
							<nav className="flex items-center sm:justify-center gap-4 flex-1 md:gap-2 lg:gap-4 pl-3 sm:pl-0">
								{ steps.map(
									(
										{
											path,
											layoutConfig: {
												name,
												hideStep,
												stepNumber,
											},
										},
										stepIdx
									) =>
										hideStep ? (
											<Fragment key={ stepIdx } />
										) : (
											<Fragment key={ stepIdx }>
												<div
													className={ classNames(
														'flex items-center',
														{
															'cursor-pointer':
																currentStep >
																	stepIdx &&
																currentStep <=
																	getStepIndex(
																		'/features'
																	) &&
																! loadingNextStep,
														}
													) }
													key={ stepIdx }
													onClick={ moveToStep(
														path,
														stepIdx
													) }
												>
													<div
														className={ classNames(
															'flex items-center gap-2'
														) }
													>
														<div
															className={ classNames(
																'rounded-full border border-border-primary text-xs font-medium flex items-center justify-center w-5 h-5',
																dynamicStepClassNames(
																	currentStep,
																	stepIdx
																),
																currentStep !==
																	stepIdx &&
																	'max-sm:h-2 max-sm:w-2'
															) }
														>
															{ renderStepContent(
																stepIdx,
																currentStep,
																stepNumber
															) }
														</div>
														<div
															className={ classNames(
																'hidden md:block text-sm font-normal text-secondary-text md:text-xs lg:text-sm',
																currentStep ===
																	stepIdx &&
																	'text-accent-st'
															) }
														>
															{ name }
														</div>
													</div>
												</div>
												{ steps.length - 1 > stepIdx &&
													breakPoint !== 'sm' &&
													breakPoint !== 'xs' &&
													! (
														steps[ stepIdx + 1 ]
															?.layoutConfig
															?.hideStep &&
														steps[ stepIdx + 1 ]
															?.layoutConfig
															?.screen === 'done'
													) && (
														<div
															className={ classNames(
																'w-8 h-px self-center md:w-4 lg:w-8',
																dynamicClass(
																	currentStep,
																	stepIdx
																)
															) }
														/>
													) }
											</Fragment>
										)
								) }
							</nav>
						) }
						{ /* Close button */ }
						{ /* Do not show on Migration step */ }

						{ getStepIndex( '/done' ) !== currentStep &&
							getStepIndex( '/building-website' ) !==
								currentStep && (
								<div className="[grid-area:1/3] !mr-5 flex items-center justify-center mx-auto">
									{ show_zip_plan && authenticated && (
										<>
											<button
												onClick={ () =>
													setPlanInformationModal( {
														planInformationModal,
														open: true,
													} )
												}
												className="border px-1.5 py-0.5 font-semibold border-blue-crayola text-xs rounded text-blue-crayola"
											>
												{ active_plan?.name }
											</button>
											<span className="mx-3 h-4 w-[1px] bg-border-tertiary"></span>
										</>
									) }
									<AiBuilderExitButton exitButtonClassName="text-icon-tertiary hover:text-icon-secondary" />
								</div>
							) }
					</header>
				) }
				<main
					id="sp-onboarding-content-wrapper"
					className="flex-1 overflow-x-hidden h-full bg-container-background"
				>
					<ErrorBoundary>
						<div className="h-full w-full relative flex">
							<div
								className={ classNames(
									'w-full max-h-full flex flex-col flex-auto items-center overflow-y-auto',
									! isAuthScreen &&
										'px-5 pt-5 [&:has(.max-w-container)]:pb-5 md:px-10 md:pt-10 md:[&:has(.max-w-container)]:pb-10 lg:px-14 lg:pt-14 lg:[&:has(.max-w-container)]:pb-14 xl:px-20 xl:pt-16 xl:[&:has(.max-w-container)]:pb-20',
									steps[ currentStep ]?.layoutConfig
										?.contentClassName
								) }
							>
								{ /* Renders page content */ }
								<Outlet />
							</div>
						</div>
					</ErrorBoundary>
				</main>
				<LimitExceedModal />
				<ContinueProgressModal />
				<ConfirmationStartOverModal />
				<SignupLoginModal />
				<ApiErrorModel />
				<PlanInformationModal />
				<PlanUpgradePromoModal />
			</div>
			<div className="absolute top-0 left-0 z-20">
				<AnimatePresence>
					{ !! selectedTemplate && currentStepURL === '/design' && (
						<PreviewWebsite />
					) }
				</AnimatePresence>
			</div>
			{ /* Toaster container */ }
			<ToasterContainer />
		</>
	);
};

export default memo( OnboardingAI );

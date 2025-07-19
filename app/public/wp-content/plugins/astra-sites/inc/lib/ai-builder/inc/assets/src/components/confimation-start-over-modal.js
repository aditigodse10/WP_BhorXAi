import {
	ClipboardIcon,
	ExclamationCircleIcon,
} from '@heroicons/react/24/outline';
import { useSelect, useDispatch } from '@wordpress/data';
import { __, sprintf } from '@wordpress/i18n';
import { STORE_KEY } from '../store';
import { getLocalStorageItem, removeLocalStorageItem } from '../helpers';
import { defaultOnboardingAIState } from '../store/reducer';
import Modal from './modal';
import Button from './button';
import { useNavigateSteps } from '../router';
import { ExclamationTriangleColorfulIcon } from '../ui/icons';
import ModalTitle from './modal-title';
import { renderToString } from '@wordpress/element';
import { copyToClipboard, deleteCookie } from '../utils/helpers';

const supportLink = (
	<a
		href={ aiBuilderVars.supportLink }
		target="_blank"
		className="text-accent-st"
		rel="noreferrer"
	>
		{ __( 'contact support', 'ai-builder' ) }
	</a>
);

const ConfirmationStartOverModal = () => {
	const {
		setContinueProgressModal,
		setConfirmationStartOverModal,
		setWebsiteOnboardingAIDetails,
	} = useDispatch( STORE_KEY );
	const { navigateTo } = useNavigateSteps();
	const { confirmationStartOverModal } = useSelect( ( select ) => {
		const { getConfirmationStartOverModalInfo } = select( STORE_KEY );
		return {
			confirmationStartOverModal: getConfirmationStartOverModalInfo(),
		};
	}, [] );

	const handleStartOver = () => {
		setConfirmationStartOverModal( { open: false } );
		removeLocalStorageItem( 'ai-builder-onboarding-details' );
		setWebsiteOnboardingAIDetails( defaultOnboardingAIState );
		setContinueProgressModal( { open: false } );
		deleteCookie( 'ai-show-start-over-warning' ); // Clear the cookie.
		navigateTo( {
			to: '/',
			replace: true,
		} ); // Navigate to the first step
	};

	const handleContinue = () => {
		setConfirmationStartOverModal( { open: false } );
		setContinueProgressModal( { open: false } );
	};

	const savedData = getLocalStorageItem( 'ai-builder-onboarding-details' );
	const { primaryText = '', errorText = '' } =
		savedData?.importSiteProgressData?.importErrorMessages || {};
	const error = `${
		primaryText?.message || primaryText
	} ${ errorText }`.trim();
	const errorString = error ? JSON.stringify( error ) : '';

	const handleCopyDetails = () => {
		const errorInfo =
			errorString ||
			__( 'Not enough information to display.', 'ai-builder' );
		const businessName = savedData?.websiteInfo?.businessName || '';
		const description = savedData?.websiteInfo?.businessDesc || '';
		const uuid = savedData?.websiteInfo?.uuid || '';
		const details = `Error: ${ errorInfo }\nBusiness Name: ${ businessName }\nDescription: ${ description }\nUUID: ${ uuid }`;

		copyToClipboard( details );
	};

	return (
		<Modal
			open={ confirmationStartOverModal?.open }
			setOpen={ ( toggle, type ) => {
				if ( type === 'close-icon' ) {
					setConfirmationStartOverModal( { open: false } );
					setContinueProgressModal( { open: true } );
				}
			} }
			className="sm:w-full sm:max-w-2xl"
		>
			<ModalTitle>
				<ExclamationTriangleColorfulIcon className="w-6 h-6 text-alert-success" />
				<h5 className="text-lg text-zip-app-heading">
					{ __( 'Start Over?', 'ai-builder' ) }
				</h5>
			</ModalTitle>
			<div className="!mt-3 text-sm leading-5 font-normal text-zip-body-text">
				{ __(
					'Starting over will reset your previous session and consume an additional AI credit.',
					'ai-builder'
				) }
			</div>
			<div className="!mt-3 text-sm leading-5 font-normal text-zip-body-text">
				{ __(
					'Would you like to continue, or return to your previous session?',
					'ai-builder'
				) }
			</div>
			<div className="!mt-3 text-sm leading-5 font-normal text-zip-body-text">
				{ __( 'Previous Session Details', 'ai-builder' ) }
			</div>
			<div className="!my-4">
				<div className="relative mb-4 p-3 border border-solid border-border-primary rounded-md">
					<button
						className="absolute top-3 right-3 w-4"
						onClick={ handleCopyDetails }
						title={ __(
							'Copy details to clipboard',
							'ai-builder'
						) }
						aria-label={ __(
							'Copy details to clipboard',
							'ai-builder'
						) }
					>
						<ClipboardIcon />
					</button>
					<p className="text-zip-body-text text-xs font-normal leading-6 mr-5">
						<b>{ __( 'Error:', 'ai-builder' ) }</b>{ ' ' }
						{ errorString ||
							__(
								'Not enough information to display.',
								'ai-builder'
							) }
					</p>
					<p className="text-zip-body-text text-xs font-normal leading-6">
						<b>{ __( 'Business Name:', 'ai-builder' ) }</b>{ ' ' }
						{ savedData?.websiteInfo?.businessName }
					</p>
					<p className="text-zip-body-text text-xs font-normal leading-6">
						<b>{ __( 'Description:', 'ai-builder' ) }</b>{ ' ' }
						{ savedData?.websiteInfo?.businessDesc }
					</p>
					<p className="text-zip-body-text text-xs font-normal leading-6">
						<b>{ __( 'UUID:', 'ai-builder' ) }</b>{ ' ' }
						<code className="font-medium">
							{ savedData?.websiteInfo?.uuid }
						</code>
					</p>
				</div>

				<p className="!mt-4 text-xs leading-5 font-normal text-secondary-text flex">
					<ExclamationCircleIcon className="w-5 h-5 mr-2" />
					{ __(
						'Importing saved sites won’t be exhausting your AI site generation count.',
						'ai-builder'
					) }
				</p>
				<p className="!mt-4 text-xs leading-5 font-normal text-secondary-text flex">
					<ExclamationCircleIcon className="min-w-5 h-5 mr-2" />
					<span
						dangerouslySetInnerHTML={ {
							__html: sprintf(
								/* translators: %1$s: Contact us link */
								__(
									'If the issue persists after multiple attempts, please %1$s for assistance. Copy and share the above details with our support team to help us resolve the issue quickly.',
									'ai-builder'
								),
								renderToString( supportLink )
							),
						} }
					/>
				</p>

				<div className="flex items-center gap-3 justify-center mt-8 flex-col xs:flex-row">
					<Button
						type="submit"
						variant="primary"
						size="medium"
						className="min-w-[206px] text-sm font-semibold leading-5 px-5 w-full xs:w-auto"
						onClick={ handleContinue }
					>
						{ __( 'Resume Previous Session', 'ai-builder' ) }
					</Button>
					<Button
						variant="white"
						size="medium"
						onClick={ handleStartOver }
						className="min-w-[206px] text-sm font-semibold leading-5 w-full xs:w-auto"
					>
						{ __( 'Start Over', 'ai-builder' ) }
					</Button>
				</div>
			</div>
		</Modal>
	);
};

export default ConfirmationStartOverModal;

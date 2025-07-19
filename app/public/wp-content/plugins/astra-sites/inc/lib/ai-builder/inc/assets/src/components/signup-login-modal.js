import { useSelect, useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { STORE_KEY } from '../store';
import Modal from './modal';
import Button from './button';
const { imageDir } = aiBuilderVars;
import { CheckIcon } from '@heroicons/react/24/outline';

const SignupLoginModal = () => {
	const { setSignupLoginModal } = useDispatch( STORE_KEY );
	const { signupLoginModal } = useSelect( ( select ) => {
		const { getSignupLoginModalInfo } = select( STORE_KEY );
		return {
			signupLoginModal: getSignupLoginModalInfo(),
		};
	}, [] );
	const { zipwp_auth } = wpApiSettings || {};
	const { screen_url, redirect_url, source, partner_id } = zipwp_auth || {};
	const { isPremiumTemplate } = signupLoginModal || {};

	const encodedRedirectUrl = encodeURIComponent(
		redirect_url +
			'&should_resume=1&security=' +
			aiBuilderVars.zipwp_auth_nonce
	);

	const handleClickNext = ( ask = 'register' ) => {
		const currentUrl = window.location.href;
		const currentUrlObj = new URL( currentUrl );
		currentUrlObj.hash = '';

		// add should_resume=1 and skip_redirect_last_step=1 to the URL
		currentUrlObj.searchParams.set( 'should_resume', '1' );
		currentUrlObj.searchParams.set( 'skip_redirect_last_step', '1' );

		// change hash to /design
		currentUrlObj.hash = '/design';

		const newUrl = currentUrlObj.toString();

		let url = `${ screen_url }?type=token&redirect_url=${ encodedRedirectUrl }&ask=/${ ask }&source=${ source }${
			partner_id ? `&aff=${ partner_id }` : ''
		}`;

		// if it's a premium template, add premium_design=true to the URL
		// so zipwp can redirect back to designs page if user wants to change design
		if ( isPremiumTemplate ) {
			url += `&premium_design=true&change_design_redirect=${ encodeURIComponent(
				newUrl
			) }`;
		}

		window.location.href = url;
		setSignupLoginModal( { open: false } );
	};

	const handleCloseModal = () => {
		setSignupLoginModal( { open: false } );
	};

	return (
		<Modal
			open={ signupLoginModal?.open }
			setOpen={ ( toggle, type ) => {
				if ( type === 'close-icon' ) {
					handleCloseModal();
				}
			} }
			width={ 480 }
			height="408"
			overflowHidden={ false }
			className={ 'px-8 pt-8 pb-5 font-sans' }
		>
			<div>
				<div className="flex items-center gap-3">
					{ /* <ClipboardIcon className="w-8 h-8 text-accent-st" /> */ }
					<img
						width={ 237 }
						src={ `${ imageDir }/st-zipwp-logo.png` }
						alt=""
					/>
				</div>

				<div className="mt-6">
					<div className="text-zip-body-text text-base font-normal leading-6 flex flex-col space-y-4">
						<h2 className="font-bold leading-6">
							Great Job! Your Site is Ready! 🎉
						</h2>

						<p className="text-base text-light-theme-text-inactive font-normal leading-5">
							{ __(
								'Sign up for a free ZipWP account to import and customize your website!',
								'ai-builder'
							) }
						</p>
					</div>
					<div className="mt-5">
						<ul className="list-none space-y-2">
							<li className="flex items-center text-base leading-5 font-normal">
								<CheckIcon
									strokeWidth={ 2 }
									className="w-5 h-5 text-light-theme-highlight-cta mr-2"
								/>
								<span className="text-black">
									{ __(
										'Customize your website with ease',
										'ai-builder'
									) }
								</span>
							</li>
							<li className="flex items-center">
								<CheckIcon
									strokeWidth={ 2 }
									className="w-5 h-5 text-light-theme-highlight-cta mr-2"
								/>
								<span className="text-black">
									{ __(
										'Launch faster than ever',
										'ai-builder'
									) }
								</span>
							</li>
							<li className="flex items-center">
								<CheckIcon
									strokeWidth={ 2 }
									className="w-5 h-5 text-light-theme-highlight-cta mr-2"
								/>
								<span className="text-black">
									{ __(
										"Need help? We're a message away",
										'ai-builder'
									) }
								</span>
							</li>
						</ul>
					</div>
					<div className="flex items-center gap-3 justify-center mt-9 flex-col">
						<Button
							type="submit"
							variant="primary"
							size="medium"
							className="min-w-full h-[40px] text-sm font-semibold leading-5 px-5 w-full xs:w-auto"
							onClick={ () => {
								handleClickNext( 'register' );
							} }
						>
							{ __( 'Create ZipWP Account', 'ai-builder' ) }
						</Button>
						<span className="text-sm">
							Already have an account?{ ' ' }
							<span
								className="text-accent-st cursor-pointer hover:underline"
								onClick={ () => {
									handleClickNext( 'login' );
								} }
							>
								{ ' ' }
								Click here to login.
							</span>
						</span>
					</div>
				</div>
			</div>
		</Modal>
	);
};

export default SignupLoginModal;

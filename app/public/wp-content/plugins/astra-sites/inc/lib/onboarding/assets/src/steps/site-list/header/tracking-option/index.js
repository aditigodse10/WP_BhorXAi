import { __, sprintf } from '@wordpress/i18n';
import ICONS from '../../../../../icons';
import ToggleSwitch from '../../../../components/toggle-switch';
import Tooltip from '../../../../components/tooltip';
import { useEffect, useRef, useState } from 'react';
import {
	getWhileLabelName,
	whiteLabelEnabled,
} from '../../../../utils/functions';

const TrackingOption = () => {
	const [ isOpen, setIsOpen ] = useState( false );
	const [ bsfUsageTracking, setBSFUsageTracking ] = useState(
		!! starterTemplates?.bsfUsageTracking
	);
	const [ response, setResponse ] = useState( {} );

	const wrapperRef = useRef( null );

	const updateBSFUsageTracking = () => {
		setBSFUsageTracking( ! bsfUsageTracking );

		// Send an API request to enable/disable the usage analytics.
		const formData = new FormData();
		formData.append( 'action', 'bsf_analytics_optin_status' );
		formData.append( '_ajax_nonce', astraSitesVars?._ajax_nonce );
		formData.append( 'bsfUsageTracking', ! bsfUsageTracking );
		fetch( astraSitesVars?.ajaxurl, {
			method: 'POST',
			body: formData,
		} )
			.then( ( res ) => res.json() )
			.then( ( res ) => {
				if ( res.success ) {
					starterTemplates.bsfUsageTracking = ! bsfUsageTracking;
				} else {
					// Revert the optimistic UI change if backend rejected the request.
					setBSFUsageTracking( bsfUsageTracking );
					setResponse( { isError: true, message: res?.data } );
				}
			} )
			.catch( ( error ) => {
				// Revert the optimistic UI change on error.
				setBSFUsageTracking( bsfUsageTracking );

				/* eslint-disable-next-line no-console -- We are displaying errors in the console. */
				console.error( error );
				setResponse( { isError: true, message: error?.message } );
			} );
	};

	useEffect( () => {
		const handleClickOutside = ( event ) => {
			if (
				wrapperRef.current &&
				! wrapperRef.current.contains( event.target )
			) {
				setIsOpen( false );
			}
		};

		document.addEventListener( 'mousedown', handleClickOutside );
		return () => {
			document.removeEventListener( 'mousedown', handleClickOutside );
		};
	}, [] );

	return (
		<div id="st-options" className="relative" ref={ wrapperRef }>
			<Tooltip content={ __( 'Analytics', 'astra-sites' ) }>
				<button
					className="inline-flex mx-1 mb-1 p-3 bg-transparent border-none cursor-pointer"
					onClick={ () => setIsOpen( ! isOpen ) }
				>
					{ ICONS.options }
				</button>
			</Tooltip>

			{ isOpen && (
				<div
					id="st-options-popup-wrapper"
					className="fixed top-[57px] right-3 w-[min(calc(100%-24px),384px)] flex gap-3 p-2.5 bg-white border border-solid border-[#E6E6EF] rounded-md z-10 md:top-[78px]"
					style={ {
						boxShadow: '0 10px 32px -12px #95A0B266',
					} }
				>
					<div className="relative top-0.5">
						<ToggleSwitch
							onChange={ updateBSFUsageTracking }
							value={ bsfUsageTracking }
							requiredClass={
								bsfUsageTracking
									? 'bg-accent-st-secondary'
									: 'bg-border-tertiary'
							}
						/>
					</div>

					<div className="flex flex-col gap-1">
						{ /* eslint-disable-next-line jsx-a11y/no-noninteractive-element-interactions */ }
						<h6
							className="cursor-pointer"
							onClick={ updateBSFUsageTracking }
						>
							{ sprintf(
								// translators: %s: Starter Templates or White Label name.
								__( 'Contribute to %s', 'astra-sites' ),
								whiteLabelEnabled()
									? getWhileLabelName()
									: __( 'Starter Templates', 'astra-sites' )
							) }
						</h6>

						<p className="!text-xs">
							{ __(
								'Collect non-sensitive information from your website, such as the PHP version and features used, to help us fix bugs faster, make smarter decisions, and build features that actually matter to you.',
								'astra-sites'
							) }{ ' ' }
							<a
								className="!inline !text-xs !underline"
								href="https://store.brainstormforce.com/usage-tracking/?utm_source=st_header&utm_medium=st_dashboard&utm_campaign=usage_tracking"
								rel="noopener noreferrer"
								target="_blank"
							>
								{ __( 'Learn More', 'astra-sites' ) }
							</a>
						</p>

						{ response?.message && (
							<p
								className={ `!text-xs ${
									response?.isError
										? '!text-red-600'
										: '!text-green-600'
								}` }
							>
								{ response?.message }
							</p>
						) }
					</div>
				</div>
			) }
		</div>
	);
};

export default TrackingOption;

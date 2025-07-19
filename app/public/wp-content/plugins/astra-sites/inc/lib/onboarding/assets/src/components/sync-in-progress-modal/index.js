import { useState, useEffect } from 'react';
import { __ } from '@wordpress/i18n';
import { useStateValue } from '../../store/store';
import ICONS from '../../../icons';
import { classNames } from '../../utils/functions';
import { Line } from 'rc-progress';

const SyncInProgressModal = ( { open = false } ) => {
	const [ { syncPageCount, syncPageInProgress } ] = useStateValue();

	const getProgressPercent = () => {
		if ( syncPageCount > 0 ) {
			return ( syncPageInProgress / syncPageCount ) * 100;
		}
		return 0;
	};

	const [ progress, setProgress ] = useState( getProgressPercent() );

	useEffect( () => {
		if ( syncPageInProgress > 0 ) {
			setProgress( getProgressPercent() );
		}
	}, [ syncPageInProgress ] );

	return (
		<div
			className={ classNames(
				'w-full absolute hidden h-screen top-0 left-0 bg-st-background-secondary/80',
				open && 'flex justify-center items-center'
			) }
		>
			<div className="bg-white w-[296px] rounded-lg shadow-2xl flex flex-col items-center justify-center -mt-36 p-5">
				<div className="flex justify-between">
					<p className="!text-base !font-semibold text-nav-active">
						{ __( 'Syncing Templates Library…', 'astra-sites' ) }
					</p>
					<div className="flex items-center justify-center text-blue-500 animate-spin w-[15px] ml-3">
						{ ICONS.reloadIcon }
					</div>
				</div>
				<p className="text-sm text-[#4B5563] text-center !mt-1">
					{ __(
						'Updating the library to include all the latest templates.',
						'astra-sites'
					) }
				</p>
				{ syncPageCount > 0 ? (
					<div className="progress-bar w-full flex justify-between mt-4">
						<Line
							percent={ progress }
							strokeWidth={ 2 }
							trailWidth={ 2 }
							trailColor="#E5E7EB"
							strokeColor="#3B82F6"
						/>
					</div>
				) : (
					''
				) }
			</div>
		</div>
	);
};

export default SyncInProgressModal;

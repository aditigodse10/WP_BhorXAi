import { classNames } from '../helpers';

const Heading = ( { heading, subHeading, className, subClassName } ) => {
	return (
		<div className={ classNames( 'space-y-3', className ) }>
			{ !! heading && (
				<div className="text-[28px] text-heading-text text-[1.75rem] font-semibold">
					{ heading }
				</div>
			) }
			{ !! subHeading && (
				<p
					className={ classNames(
						'text-body-text text-base font-normal leading-6',
						subClassName
					) }
				>
					{ subHeading }
				</p>
			) }
		</div>
	);
};

export default Heading;

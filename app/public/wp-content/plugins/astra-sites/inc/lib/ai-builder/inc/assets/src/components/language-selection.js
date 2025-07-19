import { useSelect, useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import LanguageOptions from './language-options';
import { STORE_KEY } from '../store';

const LanguageSelection = () => {
	const { setWebsiteLanguageAIStep } = useDispatch( STORE_KEY );

	const { siteLanguage, siteLanguageList } = useSelect( ( select ) => {
		const { getAIStepData } = select( STORE_KEY );
		return getAIStepData();
	} );

	const handleSelectLanguage = ( lang ) => {
		setWebsiteLanguageAIStep( lang.code );
	};

	return (
		<div className="flex-1 space-y-2">
			<label className={ `zw-sm-medium text-app-heading` }>
				{ __( 'This website will be in:', 'ai-builder' ) }
			</label>
			{ ! siteLanguageList || siteLanguageList.length === 0 ? (
				<div className="h-[40px] w-[100%] inline-flex justify-start items-center gap-2 border border-solid border-border-tertiary py-2 pl-3 pr-8 rounded-md shadow-sm">
					<div className="w-8 h-full bg-gray-300 animate-pulse" />
					<span className="!shrink-0 w-px h-[14px] bg-border-tertiary" />
					<div className="w-full h-full bg-gray-300 animate-pulse" />
				</div>
			) : (
				<LanguageOptions
					onSelect={ handleSelectLanguage }
					value={ siteLanguageList.find(
						( lang ) => lang.code === siteLanguage
					) }
					showLabel={ false }
					classNameParent="w-full"
					classNameChild="py-2 pl-4 pr-12"
				/>
			) }
		</div>
	);
};

export default LanguageSelection;

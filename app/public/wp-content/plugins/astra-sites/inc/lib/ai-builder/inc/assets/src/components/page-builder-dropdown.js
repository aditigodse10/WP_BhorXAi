import { Fragment, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { ChevronDownIcon } from '@heroicons/react/24/outline';
import DropdownList from './dropdown-list';
import { classNames } from '../helpers';

const {
	imageDir,
	isBeaverBuilderDisabled,
	isElementorDisabled,
	supportedPageBuilders = [],
} = aiBuilderVars;

const PageBuilderDropdown = () => {
	const buildersList = [
		{
			id: 'gutenberg',
			title: __( 'Block Editor', 'ai-builder' ),
			image: `${ imageDir }block-editor.svg`,
		},
	];

	if ( ! isElementorDisabled ) {
		buildersList.push( {
			id: 'elementor',
			title: __( 'Elementor', 'ai-builder' ),
			image: `${ imageDir }elementor.svg`,
		} );
	}

	if ( ! isBeaverBuilderDisabled ) {
		buildersList.push( {
			id: 'beaver-builder',
			title: __( 'Beaver Builder', 'ai-builder' ),
			image: `${ imageDir }beaver-builder.svg`,
		} );
	}

	buildersList.push( {
		id: 'ai-builder',
		title: __( 'AI Website Builder', 'ai-builder' ),
		image: `${ imageDir }ai-builder.svg`,
	} );

	const [ selectedBuilder, setSelectedBuilder ] = useState(
		buildersList.at( -1 )
	);

	return (
		<DropdownList
			by="id"
			value={ selectedBuilder }
			onChange={ ( value ) => {
				if ( value.id === buildersList.at( -1 ).id ) {
					return;
				}
				setSelectedBuilder( value );
				window.location = `${ aiBuilderVars.adminUrl }themes.php?page=starter-templates&builder=${ value.id }`;
			} }
		>
			<div className="relative">
				<DropdownList.Button className="flex items-center justify-between gap-2 min-w-[190px] w-fit py-[28px] px-[20px] border-y-0 border-r-0 border-l border-border-primary shadow-none bg-transparent rounded-none text-sm text-zip-body-text cursor-pointer">
					<div className="flex items-center gap-2">
						<img
							className="w-5 h-5"
							src={ selectedBuilder.image }
							alt={ selectedBuilder.title }
						/>
						<span className="truncate">
							{ selectedBuilder.title }
						</span>
					</div>
					<ChevronDownIcon className="w-5 h-5 text-zip-body-text" />
				</DropdownList.Button>
				<DropdownList.Options className="mt-0.5 p-0 rounded-t-none bg-white shadow-[1px_2px_5px_1px_rgba(0,0,0,0.15)]">
					{ buildersList.map( ( builder ) => (
						<DropdownList.Option
							key={ builder.id }
							as={ Fragment }
							value={ builder }
							className="py-3 px-2 hover:bg-[#F9FAFB] cursor-pointer"
						>
							<div className="flex items-center gap-2 text-sm font-normal">
								<img
									className="w-5 h-5"
									src={ builder.image }
									alt={ builder.title }
								/>
								<span>{ builder.title }</span>
							</div>
						</DropdownList.Option>
					) ) }
				</DropdownList.Options>
			</div>
		</DropdownList>
	);
};

export const SelectTemplatePageBuilderDropdown = ( {
	selectedBuilder,
	onChange,
} ) => {
	const buildersList = [
		{
			id: 'spectra',
			title: __( 'Block Editor', 'ai-builder' ),
			image: `${ imageDir }block-editor.svg`,
		},
	];

	if (
		! isElementorDisabled &&
		supportedPageBuilders?.length &&
		supportedPageBuilders.includes( 'elementor' )
	) {
		buildersList.push( {
			id: 'elementor',
			title: __( 'Elementor (Beta)', 'ai-builder' ),
			image: `${ imageDir }elementor.svg`,
		} );
	}

	return (
		<>
			<DropdownList
				by="id"
				value={ buildersList.find(
					( builder ) => builder.id === selectedBuilder
				) }
				onChange={ onChange }
			>
				<div className="relative">
					<DropdownList.Button
						className={ classNames(
							'w-[200px] h-12 border-none right-0 shadow-none  justify-between flex rounded-none items-center pl-4 pr-3 text-sm font-semibold leading-5  hover:bg-[#F4F7FB] rounded-l-md cursor-pointer'
						) }
					>
						<div className="flex items-center gap-2">
							<img
								className="w-5 h-5"
								src={
									buildersList.find(
										( builder ) =>
											builder.id === selectedBuilder
									)?.image
								}
								alt={
									buildersList.find(
										( builder ) =>
											builder.id === selectedBuilder
									)?.title
								}
							/>
							<span className="truncate">
								{
									buildersList.find(
										( builder ) =>
											builder.id === selectedBuilder
									)?.title
								}
							</span>
						</div>
						<ChevronDownIcon className="w-5 h-5 text-zip-body-text" />
					</DropdownList.Button>
					<DropdownList.Options className="py-2 px-3 gap-2 space-y-1 w-[200px]">
						{ buildersList.map( ( builder ) => (
							<DropdownList.Option
								key={ builder.id }
								as={ Fragment }
								value={ builder }
								className={
									'p-2 hover:bg-[#F9FAFB] cursor-pointer'
								}
							>
								<div className="flex items-center gap-2 text-sm font-normal">
									<img
										className="w-5 h-5"
										src={ builder.image }
										alt={ builder.title }
									/>
									<span
										className={
											selectedBuilder === builder.id
												? 'text-app-heading font-semibold'
												: 'text-app-text group-hover:text-app-heading'
										}
									>
										{ builder.title }
									</span>
								</div>
							</DropdownList.Option>
						) ) }
					</DropdownList.Options>
				</div>
			</DropdownList>
		</>
	);
};

export default PageBuilderDropdown;

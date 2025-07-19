import { useState } from 'react';
import withModals from '../hoc/withModals';
import { useModal } from '@ebay/nice-modal-react';
import Button from './button';
import Modal from './modal';
import ModalTitle from './modal-title';
import LoadingSpinner from './loading-spinner';
import { __ } from '@wordpress/i18n';
import { CircleStackIcon } from '@heroicons/react/24/outline';

const creditConfirmModalName = 'CREDIT_CONFIRM_MODAL';

const CreditConfirmModal = ( { onCancel, onConfirm } ) => {
	const modalRef = useModal( creditConfirmModalName );

	const [ isConfirming, setIsConfirming ] = useState( false );

	const handleCancel = () => {
		if ( onCancel ) {
			onCancel();
		}
		modalRef.resolve( false );
		modalRef.hide();
		modalRef.remove();
	};

	const handleConfirm = async () => {
		setIsConfirming( true );
		try {
			if ( onConfirm ) {
				await onConfirm();
			}
			modalRef.resolve( true );
			modalRef.hide();
			modalRef.remove();
		} catch ( error ) {
			console.error( 'Error confirming action:', error );
		} finally {
			setIsConfirming( false );
		}
	};

	const remaining =
		aiBuilderVars?.zip_plans?.plan_data?.remaining?.ai_sites_count || 0;
	const total =
		aiBuilderVars?.zip_plans?.plan_data?.limit?.ai_sites_count || 0;

	return (
		<Modal
			open={ modalRef.visible }
			setOpen={ handleCancel }
			onFullyClose={ modalRef.remove }
			width={ 480 }
			className={ '!p-6' }
		>
			<ModalTitle>
				<span className="flex items-center space-x-1 gap-2">
					<CircleStackIcon className="w-6 h-6 " />
					<div className="font-semibold text-lg text-app-heading">
						{ __( 'Confirm Credit Usage', 'ai-builder' ) }
					</div>
				</span>
			</ModalTitle>
			<div className="text-zip-body-text !mt-5 text-base">
				You have{ ' ' }
				<span className="font-semibold text-app-heading">
					{ remaining } out of { total } AI Site Generations
				</span>{ ' ' }
				remaining in your account. Building this website will use{ ' ' }
				<span className="font-semibold text-app-heading">
					1 { __( 'credit', 'ai-builder' ) }
				</span>{ ' ' }
				from your account. Are you sure you want to proceed?
			</div>

			<div className="flex flex-col pt-2 !mt-5 gap-y-5">
				<div className="flex gap-4 items-center space-x-3 flex-col xs:flex-row">
					<Button
						className="w-full h-10 text-sm"
						variant="primary"
						disabled={ isConfirming }
						onClick={ handleConfirm }
					>
						{ isConfirming ? (
							<LoadingSpinner />
						) : (
							__( 'Confirm and Proceed', 'ai-builder' )
						) }
					</Button>
					<Button
						className="w-full h-10 text-sm border-gray-200 text-black"
						variant="white"
						disabled={ isConfirming }
						onClick={ handleCancel }
					>
						{ __( 'Cancel', 'ai-builder' ) }
					</Button>
				</div>
			</div>
		</Modal>
	);
};

export default withModals( CreditConfirmModal, creditConfirmModalName );

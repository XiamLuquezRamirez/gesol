import AppLayout from '@/Layouts/AppLayout';
import DeleteUserForm from './Partials/DeleteUserForm';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';
import { Head } from '@inertiajs/react';

export default function Edit({ mustVerifyEmail, status }) {
    return (
        <AppLayout title="Mi perfil">
            <Head title="Mi perfil" />

            <div className="p-6 w-full space-y-5">
                <div>
                    <h2 className="text-lg font-semibold text-slate-800">Mi perfil</h2>
                    <p className="text-sm text-slate-500 mt-0.5">Administra tu información personal y contraseña.</p>
                </div>

                <div className="bg-white rounded-xl border border-slate-200 p-6">
                    <UpdateProfileInformationForm mustVerifyEmail={mustVerifyEmail} status={status} />
                </div>

                <div className="bg-white rounded-xl border border-slate-200 p-6">
                    <UpdatePasswordForm />
                </div>

                <div className="bg-white rounded-xl border border-slate-200 p-6">
                    <DeleteUserForm />
                </div>
            </div>
        </AppLayout>
    );
}

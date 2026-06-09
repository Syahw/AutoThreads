import Swal from 'sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';

function isDarkMode() {
  return document.documentElement.classList.contains('dark');
}

function getSwalTheme() {
  if (isDarkMode()) {
    return {
      background: '#0f172a',
      color: '#f1f5f9',
      confirmButtonColor: '#6366f1',
      cancelButtonColor: '#475569',
      denyButtonColor: '#d97706',
    };
  }

  return {
    background: '#ffffff',
    color: '#0f172a',
    confirmButtonColor: '#6366f1',
    cancelButtonColor: '#64748b',
    denyButtonColor: '#f59e0b',
  };
}

function baseOptions(overrides = {}) {
  const theme = getSwalTheme();

  return {
    background: theme.background,
    color: theme.color,
    confirmButtonColor: theme.confirmButtonColor,
    cancelButtonColor: theme.cancelButtonColor,
    denyButtonColor: theme.denyButtonColor,
    buttonsStyling: true,
    customClass: {
      container: 'swal-app-container',
      popup: 'swal-app-popup',
      title: 'swal-app-title',
      htmlContainer: 'swal-app-html',
      actions: 'swal-app-actions',
      confirmButton: 'swal-app-btn',
      cancelButton: 'swal-app-btn',
      denyButton: 'swal-app-btn',
    },
    ...overrides,
  };
}

export async function confirmAction({
  title,
  text,
  html,
  confirmText = 'Confirm',
  cancelText = 'Cancel',
  icon = 'question',
}) {
  const result = await Swal.fire(baseOptions({
    title,
    text,
    html,
    icon,
    showCancelButton: true,
    confirmButtonText: confirmText,
    cancelButtonText: cancelText,
  }));

  return result.isConfirmed;
}

export async function confirmDelete(text, title = 'Delete post?') {
  return confirmAction({
    title,
    text,
    confirmText: 'Delete',
    icon: 'warning',
  });
}

export async function confirmRevertToDraft(isScheduled) {
  return confirmAction({
    title: 'Revert to draft?',
    text: isScheduled
      ? 'This will cancel the scheduled publish and move the post back to drafts.'
      : 'This post will move back to drafts so you can edit or approve again.',
    confirmText: 'Revert to draft',
    icon: 'question',
  });
}

/**
 * @returns {Promise<{ proceed: boolean, deleteFromThreads?: boolean }>}
 */
export async function confirmPostedDelete() {
  const result = await Swal.fire(baseOptions({
    title: 'Delete published post?',
    html: '<p class="swal-app-muted">Choose whether to remove the live thread on Threads as well.</p>',
    icon: 'warning',
    showCancelButton: true,
    showDenyButton: true,
    confirmButtonText: 'Threads + AutoThreads',
    denyButtonText: 'AutoThreads only',
    cancelButtonText: 'Cancel',
  }));

  if (result.isConfirmed) {
    return { proceed: true, deleteFromThreads: true };
  }
  if (result.isDenied) {
    return { proceed: true, deleteFromThreads: false };
  }

  return { proceed: false };
}

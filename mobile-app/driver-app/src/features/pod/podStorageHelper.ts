import { Directory, File, Paths } from 'expo-file-system';

function generateUUID(): string {
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
    const r = (Math.random() * 16) | 0;
    const v = c === 'x' ? r : (r & 0x3) | 0x8;
    return v.toString(16);
  });
}

function getEvidenceDirectory(): Directory {
  const dir = new Directory(Paths.document, 'pod_evidence');
  if (!dir.exists) {
    dir.create();
  }
  return dir;
}

export interface PersistedEvidenceFiles {
  photoUri: string;
  signatureUri: string;
}

export async function persistPodEvidenceFiles(
  assignmentId: string | number,
  tempPhotoUri: string,
  strokes: any[]
): Promise<PersistedEvidenceFiles> {
  const dir = getEvidenceDirectory();
  const uuid = generateUUID();

  // 1. Copy Photo to Persistent App Storage
  const persistentPhotoFile = new File(dir, `pod-photo-${assignmentId}-${uuid}.jpg`);
  if (!persistentPhotoFile.exists) {
    persistentPhotoFile.create();
  }
  const sourcePhotoFile = new File(tempPhotoUri);
  if (sourcePhotoFile.exists) {
    sourcePhotoFile.copy(persistentPhotoFile);
  }

  // 2. Write Signature JSON to Persistent App Storage
  const persistentSigFile = new File(dir, `pod-signature-${assignmentId}-${uuid}.json`);
  if (!persistentSigFile.exists) {
    persistentSigFile.create();
  }
  persistentSigFile.write(
    JSON.stringify({ strokes, captured_at: new Date().toISOString() })
  );

  return {
    photoUri: persistentPhotoFile.uri,
    signatureUri: persistentSigFile.uri,
  };
}

export async function cleanupPodEvidenceFiles(
  photoUri?: string | null,
  signatureUri?: string | null
): Promise<void> {
  try {
    if (photoUri) {
      const f = new File(photoUri);
      if (f.exists) {
        f.delete();
      }
    }
    if (signatureUri) {
      const s = new File(signatureUri);
      if (s.exists) {
        s.delete();
      }
    }
  } catch (e) {
    console.warn('[POD Storage] Failed to delete local evidence files:', e);
  }
}

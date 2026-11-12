import { useEffect, useState } from 'react';
import { ActivityIndicator, Image, Linking, Platform, Pressable, StyleSheet, Text, View } from 'react-native';
import {
  downloadClientShipmentCertificate,
  getClientShipmentDeliveryProof,
  getClientShipmentPodFile,
  type ClientDeliveryProofData,
} from './shipmentsApi';
import { SignatureRenderer } from '../../components/SignatureRenderer';

interface ProofOfDeliveryCardProps {
  shipmentId: string;
}

export function ProofOfDeliveryCard({ shipmentId }: ProofOfDeliveryCardProps) {
  const [loading, setLoading] = useState(true);
  const [proof, setProof] = useState<ClientDeliveryProofData | null>(null);
  const [photoUri, setPhotoUri] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [certDownloading, setCertDownloading] = useState(false);
  const [certNotice, setCertNotice] = useState<string | null>(null);

  useEffect(() => {
    let isMounted = true;
    async function loadData() {
      setLoading(true);
      setError(null);
      try {
        const data = await getClientShipmentDeliveryProof(shipmentId);
        if (isMounted) setProof(data);

        if (data?.photo.available) {
          try {
            const file = await getClientShipmentPodFile(shipmentId);
            if (isMounted) setPhotoUri(file.uri);
          } catch {
            // photo error fallback
          }
        }
      } catch (e: any) {
        if (isMounted) {
          setError(e.message || 'Could not load proof of delivery');
        }
      } finally {
        if (isMounted) setLoading(false);
      }
    }

    if (shipmentId) {
      void loadData();
    }
    return () => {
      isMounted = false;
    };
  }, [shipmentId]);

  if (loading) {
    return (
      <View style={styles.card} testID="client-pod-card-loading">
        <Text style={styles.title}>PROOF OF DELIVERY</Text>
        <View style={styles.centerBox}>
          <ActivityIndicator size="small" color="#3B82F6" />
          <Text style={styles.metaText}>Loading proof of delivery...</Text>
        </View>
      </View>
    );
  }

  if (error || !proof) {
    return null; // Return null if POD does not exist for this shipment
  }

  const openMap = () => {
    if (proof.location.latitude != null && proof.location.longitude != null) {
      const url = `https://www.google.com/maps/search/?api=1&query=${proof.location.latitude},${proof.location.longitude}`;
      void Linking.openURL(url);
    }
  };

  const conditionLabel = (proof.condition.status || 'good').toUpperCase();

  return (
    <View style={styles.card} testID="client-pod-card">
      <View style={styles.headerRow}>
        <Text style={styles.title}>PROOF OF DELIVERY</Text>
        <View style={styles.badgeSuccess}>
          <Text style={styles.badgeSuccessText}>✓ Delivery confirmed</Text>
        </View>
      </View>

      <View style={styles.grid}>
        <View style={styles.gridItem}>
          <Text style={styles.label}>Receiver</Text>
          <Text style={styles.value}>{proof.receiver.name || 'Not recorded'}</Text>
          {proof.receiver.role ? <Text style={styles.subValue}>{proof.receiver.role}</Text> : null}
        </View>
        <View style={styles.gridItem}>
          <Text style={styles.label}>Condition</Text>
          <View style={styles.badgeCondition}>
            <Text style={styles.badgeConditionText}>{conditionLabel}</Text>
          </View>
        </View>
      </View>

      {proof.deliveredAt ? (
        <View style={styles.row}>
          <Text style={styles.label}>Delivered At:</Text>
          <Text style={styles.value}>{proof.deliveredAt}</Text>
        </View>
      ) : null}

      {proof.notes ? (
        <View style={styles.section}>
          <Text style={styles.label}>Notes</Text>
          <Text style={styles.notesBox}>{proof.notes}</Text>
        </View>
      ) : null}

      {proof.location.latitude != null && proof.location.longitude != null ? (
        <View style={styles.section}>
          <View style={styles.headerRow}>
            <Text style={styles.label}>Delivery Location</Text>
            <Pressable testID="client-pod-open-map" onPress={openMap}>
              <Text style={styles.linkText}>Open Map →</Text>
            </Pressable>
          </View>
          <Text style={styles.metaText}>
            {proof.location.latitude.toFixed(6)}, {proof.location.longitude.toFixed(6)}
          </Text>
        </View>
      ) : null}

      {photoUri ? (
        <View style={styles.section}>
          <Text style={styles.label}>Delivery Photo</Text>
          <Image source={{ uri: photoUri }} style={styles.photo} testID="client-pod-photo" />
        </View>
      ) : null}

      <View style={styles.section}>
        <Text style={styles.label}>Digital Signature</Text>
        <SignatureRenderer strokes={proof.signature.strokes} />
      </View>

      <Pressable
        testID="client-pod-download-cert"
        style={styles.certButton}
        disabled={certDownloading}
        onPress={async () => {
          setCertDownloading(true);
          setCertNotice(null);
          try {
            const res = await downloadClientShipmentCertificate(shipmentId);
            if (Platform.OS === 'web') {
              const anchor = document.createElement('a');
              anchor.href = res.uri;
              anchor.download = res.filename;
              document.body.appendChild(anchor);
              anchor.click();
              document.body.removeChild(anchor);
            }
            setCertNotice('Certificate downloaded');
          } catch {
            setCertNotice('Unable to download certificate');
          } finally {
            setCertDownloading(false);
          }
        }}>
        {certDownloading ? (
          <ActivityIndicator size="small" color="#FFFFFF" />
        ) : (
          <Text style={styles.certButtonText}>Download Delivery Certificate</Text>
        )}
      </Pressable>

      {certNotice ? <Text style={styles.certNotice}>{certNotice}</Text> : null}
    </View>
  );
}

const styles = StyleSheet.create({
  card: {
    backgroundColor: '#0E1320',
    borderRadius: 16,
    borderWidth: 1,
    borderColor: 'rgba(255, 255, 255, 0.1)',
    padding: 16,
    marginVertical: 12,
    gap: 12,
  },
  headerRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  title: {
    fontSize: 11,
    fontWeight: '800',
    letterSpacing: 1.2,
    color: '#94A3B8',
  },
  badgeSuccess: {
    backgroundColor: 'rgba(16, 185, 129, 0.15)',
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 999,
    borderWidth: 1,
    borderColor: 'rgba(16, 185, 129, 0.3)',
  },
  badgeSuccessText: {
    fontSize: 11,
    fontWeight: '700',
    color: '#34D399',
  },
  grid: {
    flexDirection: 'row',
    gap: 12,
  },
  gridItem: {
    flex: 1,
  },
  label: {
    fontSize: 11,
    color: '#64748B',
    marginBottom: 2,
  },
  value: {
    fontSize: 13,
    fontWeight: '600',
    color: '#F8FAFC',
  },
  subValue: {
    fontSize: 11,
    color: '#94A3B8',
  },
  badgeCondition: {
    alignSelf: 'flex-start',
    backgroundColor: 'rgba(59, 130, 246, 0.15)',
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: 6,
    borderWidth: 1,
    borderColor: 'rgba(59, 130, 246, 0.3)',
    marginTop: 2,
  },
  badgeConditionText: {
    fontSize: 11,
    fontWeight: '700',
    color: '#60A5FA',
  },
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  section: {
    gap: 4,
  },
  notesBox: {
    fontSize: 12,
    color: '#CBD5E1',
    backgroundColor: 'rgba(15, 23, 42, 0.6)',
    padding: 10,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: 'rgba(255, 255, 255, 0.05)',
  },
  metaText: {
    fontSize: 12,
    color: '#94A3B8',
  },
  linkText: {
    fontSize: 12,
    fontWeight: '600',
    color: '#3B82F6',
  },
  photo: {
    width: '100%',
    height: 180,
    borderRadius: 8,
    backgroundColor: '#060911',
  },
  centerBox: {
    paddingVertical: 20,
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
  },
  certButton: {
    backgroundColor: '#1E40AF',
    borderRadius: 8,
    paddingVertical: 10,
    alignItems: 'center',
    justifyContent: 'center',
    marginTop: 6,
  },
  certButtonText: {
    color: '#FFFFFF',
    fontSize: 13,
    fontWeight: '700',
  },
  certNotice: {
    fontSize: 12,
    color: '#60A5FA',
    textAlign: 'center',
  },
});

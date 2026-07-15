<?php
namespace CrediTrack\Application;

use CrediTrack\Infrastructure\Audit;
use WP_Error;

final class Document_Service {
	private const TYPES = array( 'National ID', 'Proof of Address', 'Proof of Income', 'Collateral Document', 'Other' );
	private const MIMES = array( 'application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png' );

	public function upload( int $client_id, array $file, string $type ): int|WP_Error {
		if ( ! current_user_can( 'creditrack_manage_documents' ) ) { return new WP_Error( 'forbidden', 'You cannot upload client documents.' ); }
		if ( ! in_array( $type, self::TYPES, true ) ) { return new WP_Error( 'validation', 'Select a valid document type.' ); }
		global $wpdb;
		if ( ! $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}ct_clients WHERE id=%d", $client_id ) ) ) { return new WP_Error( 'not_found', 'Client not found.' ); }
		if ( UPLOAD_ERR_OK !== (int) ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) || ! is_uploaded_file( $file['tmp_name'] ?? '' ) ) { return new WP_Error( 'upload', 'Select a document to upload.' ); }
		$settings = get_option( 'creditrack_settings', array() ); $max = max( 1, (int) ( $settings['max_upload_mb'] ?? 5 ) ) * MB_IN_BYTES;
		if ( (int) $file['size'] < 1 || (int) $file['size'] > $max ) { return new WP_Error( 'upload', 'The document exceeds the configured file-size limit.' ); }
		$finfo = new \finfo( FILEINFO_MIME_TYPE ); $mime = $finfo->file( $file['tmp_name'] );
		if ( ! isset( self::MIMES[ $mime ] ) ) { return new WP_Error( 'upload', 'Only genuine PDF, JPEG, and PNG documents are accepted.' ); }
		$extension = strtolower( pathinfo( sanitize_file_name( $file['name'] ), PATHINFO_EXTENSION ) );
		if ( $extension !== self::MIMES[ $mime ] && ! ( 'jpeg' === $extension && 'jpg' === self::MIMES[ $mime ] ) ) { return new WP_Error( 'upload', 'The file extension does not match its content.' ); }
		$directory = self::directory(); if ( is_wp_error( $directory ) ) { return $directory; }
		$stored = bin2hex( random_bytes( 24 ) ) . '.' . self::MIMES[ $mime ]; $path = trailingslashit( $directory ) . $stored;
		if ( ! move_uploaded_file( $file['tmp_name'], $path ) ) { return new WP_Error( 'upload', 'The document could not be stored securely.' ); }
		$data = array( 'client_id' => $client_id, 'stored_name' => $stored, 'original_name' => sanitize_file_name( $file['name'] ), 'protected_path' => $path, 'file_size' => (int) $file['size'], 'mime_type' => $mime, 'document_type' => $type, 'uploaded_by' => get_current_user_id(), 'uploaded_at' => gmdate( 'Y-m-d H:i:s' ) );
		if ( ! $wpdb->insert( $wpdb->prefix . 'ct_client_documents', $data ) ) { wp_delete_file( $path ); return new WP_Error( 'database', 'Document metadata could not be saved.' ); }
		$id = (int) $wpdb->insert_id; Audit::record( 'document', $id, 'upload', array(), array_diff_key( $data, array( 'protected_path' => true ) ), 'Protected client document uploaded' ); return $id;
	}

	public function delete( int $id ): true|WP_Error {
		if ( ! current_user_can( 'creditrack_manage_documents' ) ) { return new WP_Error( 'forbidden', 'You cannot delete client documents.' ); }
		global $wpdb; $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ct_client_documents WHERE id=%d", $id ), ARRAY_A ); if ( ! $row ) { return new WP_Error( 'not_found', 'Document not found.' ); }
		if ( ! self::safe_path( $row['protected_path'] ) ) { return new WP_Error( 'path', 'The document path failed a security check.' ); }
		if ( false === $wpdb->delete( $wpdb->prefix . 'ct_client_documents', array( 'id' => $id ) ) ) { return new WP_Error( 'database', 'Document could not be deleted.' ); }
		wp_delete_file( $row['protected_path'] ); Audit::record( 'document', $id, 'delete', array( 'original_name' => $row['original_name'] ), array(), 'Protected client document deleted' ); return true;
	}

	public function stream( int $id ): never {
		if ( ! current_user_can( 'creditrack_read' ) ) { wp_die( 'Access denied.', '', array( 'response' => 403 ) ); }
		global $wpdb; $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ct_client_documents WHERE id=%d", $id ), ARRAY_A );
		if ( ! $row || ! self::safe_path( $row['protected_path'] ) || ! is_readable( $row['protected_path'] ) ) { wp_die( 'Document not found.', '', array( 'response' => 404 ) ); }
		Audit::record( 'document', $id, 'download', array(), array(), 'Protected client document downloaded' ); nocache_headers(); header( 'X-Content-Type-Options: nosniff' ); header( 'Content-Type: ' . $row['mime_type'] ); header( 'Content-Length: ' . (int) filesize( $row['protected_path'] ) ); header( 'Content-Disposition: attachment; filename="' . rawurlencode( $row['original_name'] ) . '"' ); readfile( $row['protected_path'] ); exit;
	}

	private static function directory(): string|WP_Error {
		$base = trailingslashit( WP_CONTENT_DIR ) . 'creditrack-private'; if ( ! wp_mkdir_p( $base ) ) { return new WP_Error( 'storage', 'Protected storage is unavailable.' ); }
		if ( ! file_exists( $base . '/index.php' ) ) { file_put_contents( $base . '/index.php', "<?php\nhttp_response_code(404);\nexit;\n" ); }
		if ( ! file_exists( $base . '/.htaccess' ) ) { file_put_contents( $base . '/.htaccess', "Require all denied\nDeny from all\n" ); }
		if ( ! file_exists( $base . '/web.config' ) ) { file_put_contents( $base . '/web.config', '<configuration><system.webServer><security><authorization><remove users="*" roles="" verbs=""/><add accessType="Deny" users="*"/></authorization></security></system.webServer></configuration>' ); }
		return $base;
	}
	private static function safe_path( string $path ): bool { $base = realpath( trailingslashit( WP_CONTENT_DIR ) . 'creditrack-private' ); $real = realpath( $path ); return $base && $real && str_starts_with( wp_normalize_path( $real ), trailingslashit( wp_normalize_path( $base ) ) ); }
}

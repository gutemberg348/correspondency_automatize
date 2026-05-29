package br.com.condominio.nativepdfshare;

import android.content.ClipData;
import android.content.Context;
import android.content.Intent;
import android.content.pm.PackageManager;
import android.content.pm.ResolveInfo;
import android.net.Uri;
import android.util.Base64;

import androidx.core.content.FileProvider;

import org.apache.cordova.CallbackContext;
import org.apache.cordova.CordovaPlugin;
import org.json.JSONArray;
import org.json.JSONObject;

import java.io.File;
import java.io.FileOutputStream;
import java.util.List;

public class NativePdfShare extends CordovaPlugin {
    @Override
    public boolean execute(String action, JSONArray args, CallbackContext callbackContext) {
        if (!"sharePdf".equals(action)) {
            return false;
        }

        JSONObject options = args.optJSONObject(0);
        cordova.getThreadPool().execute(() -> {
            try {
                sharePdf(options, callbackContext);
            } catch (Exception error) {
                callbackContext.error(error.getMessage());
            }
        });
        return true;
    }

    private void sharePdf(JSONObject options, CallbackContext callbackContext) throws Exception {
        if (options == null) {
            throw new IllegalArgumentException("Dados do PDF nao informados.");
        }

        Context context = cordova.getContext();
        String base64 = options.optString("base64", "");
        int comma = base64.indexOf(',');
        if (comma >= 0) {
            base64 = base64.substring(comma + 1);
        }

        byte[] pdfBytes = Base64.decode(base64, Base64.DEFAULT);
        if (pdfBytes.length == 0) {
            throw new IllegalArgumentException("PDF vazio.");
        }

        File shareDir = new File(context.getCacheDir(), "shared-pdf");
        if (!shareDir.exists() && !shareDir.mkdirs()) {
            throw new IllegalStateException("Nao foi possivel preparar o arquivo.");
        }

        File pdfFile = new File(shareDir, safeFileName(options.optString("filename", "correspondencia.pdf")));
        try (FileOutputStream output = new FileOutputStream(pdfFile)) {
            output.write(pdfBytes);
        }

        String authority = context.getPackageName() + ".cdv.core.file.provider";
        Uri uri = FileProvider.getUriForFile(context, authority, pdfFile);

        Intent shareIntent = new Intent(Intent.ACTION_SEND);
        shareIntent.setType("application/pdf");
        shareIntent.putExtra(Intent.EXTRA_STREAM, uri);
        shareIntent.putExtra(Intent.EXTRA_SUBJECT, options.optString("subject", "Comprovante de correspondencia"));
        String message = options.optString("message", "");
        if (!message.isEmpty()) {
            shareIntent.putExtra(Intent.EXTRA_TEXT, message);
        }
        shareIntent.setClipData(ClipData.newUri(context.getContentResolver(), pdfFile.getName(), uri));
        shareIntent.addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION);

        grantReadPermission(context, shareIntent, uri);

        Intent chooser = Intent.createChooser(shareIntent, options.optString("title", "Compartilhar PDF"));
        chooser.addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION);

        cordova.getActivity().runOnUiThread(() -> {
            try {
                cordova.getActivity().startActivity(chooser);
                callbackContext.success();
            } catch (Exception error) {
                callbackContext.error(error.getMessage());
            }
        });
    }

    private void grantReadPermission(Context context, Intent intent, Uri uri) {
        try {
            List<ResolveInfo> targets = context.getPackageManager().queryIntentActivities(intent, PackageManager.MATCH_DEFAULT_ONLY);
            for (ResolveInfo target : targets) {
                if (target.activityInfo != null && target.activityInfo.packageName != null) {
                    context.grantUriPermission(target.activityInfo.packageName, uri, Intent.FLAG_GRANT_READ_URI_PERMISSION);
                }
            }
        } catch (Exception ignored) {
            // O Android tambem recebe a permissao pelo ClipData/flags do Intent.
        }
    }

    private String safeFileName(String value) {
        String clean = value == null ? "" : value.replaceAll("[^A-Za-z0-9._-]", "-");
        if (clean.isEmpty()) {
            return "correspondencia.pdf";
        }
        if (!clean.toLowerCase().endsWith(".pdf")) {
            return clean + ".pdf";
        }
        return clean;
    }
}

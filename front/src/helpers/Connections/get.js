import getCookie from "../Cookies/getCookie";
import { useLoading } from "@/composables/useLoading";
import { useToast } from "@/composables/useToast";
import { notifyAuthFailure } from './authFailure';

const { start, stop } = useLoading();
const { fromResponse } = useToast();

function getAuthHeaders() {
  const token = localStorage.getItem("auth_token");
  return token ? { Authorization: `Bearer ${token}` } : {};
}

export default async function _get({ url = "?", callback, showLoading = true, showToast = false }) {
  try {
    if (url.includes("/api") === false) {
      new URL(url);
    }
  } catch {
    throw new Error("A url informada é inválida: " + url);
  }

  if (showLoading) start();

  try {
    const response = await fetch(url, {
      method: "GET",
      credentials: "include",
      headers: {
        "Content-Type": "application/json",
        "HTTP-XSRF-TOKEN": getCookie("XSRF_TOKEN"),
        Accept: "application/json",
        ...getAuthHeaders(),
      },
    });

    const data = await response.json();
    notifyAuthFailure(response.status);

    if (showToast || !response.ok) {
        fromResponse(response.status, data.message);
    }

    if (typeof callback === "function") {
      return callback(response.status, data);
    }

    return data;
  } catch (error) {
    if (!url.includes("buscar")) { // Avoid spamming on search
        fromResponse(500, "Falha na conexão com o servidor.");
    }
    console.error("Erro no GET:", error);
    throw error;
  } finally {
    if (showLoading) stop();
  }
}

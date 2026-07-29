import getCookie from "../Cookies/getCookie";
import { useLoading } from "@/composables/useLoading";
import { useToast } from "@/composables/useToast";
import { notifyAuthFailure } from './authFailure';

const { start, stop } = useLoading();
const { fromResponse } = useToast();

export default async function _delete({ url = "?", callback, showLoading = true, showToast = true }) {
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
      method: "DELETE",
      credentials: "include",
      headers: {
        "Content-Type": "application/json",
        "HTTP-XSRF-TOKEN": getCookie("XSRF_TOKEN"),
        Accept: "application/json",
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
    fromResponse(500, "Falha na conexão com o servidor.");
    console.error("Erro no DELETE:", error);
    throw error;
  } finally {
    if (showLoading) stop();
  }
}

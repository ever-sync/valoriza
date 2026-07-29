import getCookie from "../Cookies/getCookie";
import { useLoading } from "@/composables/useLoading";
import { useToast } from "@/composables/useToast";

const { start, stop } = useLoading();
const { fromResponse } = useToast();

export default async function _post({ url = "?", body = {}, callback, showLoading = true, showToast = true }) {
  try {
    if (url.includes("/api") === false) {
      new URL(url);
    }
  } catch {
    throw new Error("A url informada é inválida: " + url);
  }

  let parsedBody;
  try {
    parsedBody = typeof body === "string" ? JSON.parse(body) : body;
  } catch {
    throw new Error("O corpo enviado não está no formato JSON válido");
  }

  if (showLoading) start();

  try {
    const response = await fetch(url, {
      method: "POST",
      credentials: "include",
      headers: {
        "Content-Type": "application/json",
        "HTTP-XSRF-TOKEN": getCookie("XSRF_TOKEN"),
        Accept: "application/json",
      },
      body: JSON.stringify(parsedBody),
    });

    const data = await response.json();

    if (showToast || !response.ok) {
        fromResponse(response.status, data.message);
    }

    if (typeof callback === "function") {
      return callback(response.status, data);
    }

    return data;
  } catch (error) {
    fromResponse(500, "Falha na conexão com o servidor.");
    console.error("Erro no POST:", error);
    throw error;
  } finally {
    if (showLoading) stop();
  }
}

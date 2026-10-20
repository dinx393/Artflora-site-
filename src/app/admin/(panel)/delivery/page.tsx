import { getSetting } from "@/lib/settings";
import { DeliveryEditor } from "./DeliveryEditor";

export default async function Delivery() {
  const [delivery, districts] = await Promise.all([getSetting("delivery"), getSetting("districts")]);
  return <DeliveryEditor delivery={delivery} districts={districts} />;
}

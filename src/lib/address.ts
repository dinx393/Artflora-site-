// «Уручье: ул. Немига, 5, кв. 12, подъезд 2, этаж 4» — так адрес видит флорист и курьер.
// Район впереди: цену доставки считали по нему, при подтверждении заказа его сверяют с адресом.
export function fullAddress(o: { district?: string; address: string; apartment?: string; entrance?: string; floor?: string }): string {
  const parts = [
    o.address.trim(),
    o.apartment?.trim() && `кв. ${o.apartment.trim()}`,
    o.entrance?.trim() && `подъезд ${o.entrance.trim()}`,
    o.floor?.trim() && `этаж ${o.floor.trim()}`,
  ]
    .filter(Boolean)
    .join(", ");
  return o.district?.trim() ? `${o.district.trim()}: ${parts}` : parts;
}

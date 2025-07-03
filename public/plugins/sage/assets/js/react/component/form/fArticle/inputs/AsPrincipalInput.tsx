import * as React from "react";
import { useImperativeHandle, useRef } from "react";
import { Tooltip } from "@mui/material";
import { getTranslations } from "../../../../../functions/translations";
import {
  FormValidInterface,
  TriggerFormContentChanged,
} from "../../../../../interface/InputInterface";
import { TOKEN } from "../../../../../token";

let translations: any = getTranslations();

export type AsPrincipalInputState = {
  selectedDeNo: string;
  deNo: number | string;
  onAsPrincipalChangedParent: TriggerFormContentChanged;
};

export const AsPrincipalInput = React.forwardRef(
  (
    { selectedDeNo, deNo, onAsPrincipalChangedParent }: AsPrincipalInputState,
    ref,
  ) => {
    const inputRef = useRef<any>(null);
    const name = `_${TOKEN}_fArtstocks[${deNo}].asPrincipal`;

    useImperativeHandle(ref, () => ({
      async isValid(): Promise<FormValidInterface> {
        const valid = true;
        return {
          valid: valid,
          details: [
            {
              valid: valid,
              ref: ref,
              dRef: inputRef,
            },
          ],
        };
      },
    }));

    return (
      <>
        <label htmlFor={name} style={{ display: "none" }}>
          <Tooltip title={name} arrow placement="top">
            <span>{translations.words.supplierRef}</span>
          </Tooltip>
        </label>
        <div style={{ display: "flex", alignItems: "center" }}>
          <input
            id={name}
            name={name}
            type="checkbox"
            checked={selectedDeNo.toString() === deNo.toString()}
            onChange={(e) => {
              if (e.target.checked) {
                onAsPrincipalChangedParent(name, deNo.toString());
              }
            }}
            ref={inputRef}
          />
        </div>
      </>
    );
  },
);
